<?php
// =============================================
// contact.php — Traitement du formulaire
// Utilise PHPMailer + SMTP Gmail (fiable)
// =============================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/config.php';

// ── 1. Méthode autorisée ─────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

// ── 2. Anti-spam honeypot ────────────────────
if (!empty($_POST['_gotcha'])) {
    echo json_encode(['ok' => true]); // Silencieux pour ne pas alerter le bot
    exit;
}

// ── 3. Rate limiting (session) ───────────────
session_start();
$now    = time();
$window = 60;  // secondes
$maxReq = 3;   // envois max par fenêtre

$_SESSION['contact_times'] = array_filter(
    $_SESSION['contact_times'] ?? [],
    fn($t) => ($now - $t) < $window
);

if (count($_SESSION['contact_times']) >= $maxReq) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Trop de tentatives. Attendez une minute.']);
    exit;
}

// ── 4. Nettoyage des entrées ─────────────────
function clean(string $v): string {
    return trim(strip_tags($v));
}

$nom     = clean($_POST['nom']     ?? '');
$email   = clean($_POST['email']   ?? '');
$sujet   = clean($_POST['sujet']   ?? '');
$message = clean($_POST['message'] ?? '');
$ip      = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

// ── 5. Validation ────────────────────────────
$errors = [];

if (empty($nom))
    $errors[] = 'Le nom est requis.';
elseif (mb_strlen($nom) > 100)
    $errors[] = 'Le nom est trop long (100 car. max).';

if (empty($email))
    $errors[] = "L'adresse email est requise.";
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors[] = "L'adresse email est invalide.";
elseif (mb_strlen($email) > 255)
    $errors[] = "L'adresse email est trop longue.";

if (mb_strlen($sujet) > 200)
    $errors[] = 'Le sujet est trop long (200 car. max).';

if (empty($message))
    $errors[] = 'Le message est requis.';
elseif (mb_strlen($message) < 10)
    $errors[] = 'Le message doit faire au moins 10 caractères.';
elseif (mb_strlen($message) > 2000)
    $errors[] = 'Le message est trop long (2000 car. max).';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ── 6. Connexion PDO ─────────────────────────
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('[contact] PDO: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erreur serveur. Réessayez plus tard.']);
    exit;
}

// ── 7. Sauvegarde en base ─────────────────────
try {
    $stmt = $pdo->prepare(
        'INSERT INTO messages (nom, email, sujet, message, ip)
         VALUES (:nom, :email, :sujet, :message, :ip)'
    );
    $stmt->execute([
        ':nom'     => $nom,
        ':email'   => $email,
        ':sujet'   => $sujet,
        ':message' => $message,
        ':ip'      => $ip,
    ]);
    $messageId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    error_log('[contact] INSERT: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erreur serveur. Réessayez plus tard.']);
    exit;
}

// ── 8. Envoi email via PHPMailer + SMTP Gmail ─
// PHPMailer s'installe avec : composer require phpmailer/phpmailer
// Ou téléchargez les 3 fichiers depuis : https://github.com/PHPMailer/PHPMailer/tree/master/src
// et placez-les dans un dossier PHPMailer/ à côté de ce fichier.

$mailerOk    = false;
$mailerError = '';

$phpmailerPath = __DIR__ . '/PHPMailer/src/PHPMailer.php';

if (file_exists($phpmailerPath)) {
    // --- PHPMailer disponible : envoi SMTP fiable ---
    require __DIR__ . '/PHPMailer/src/Exception.php';
    require __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require __DIR__ . '/PHPMailer/src/SMTP.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception as MailException;

    $mail = new PHPMailer(true);

    try {
        // Serveur SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Expéditeur & destinataire
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress(MAIL_TO, MAIL_TO_NAME);
        $mail->addReplyTo($email, $nom); // Répondre directement à l'expéditeur

        // Sujet
        $sujetMail = $sujet
            ? "[Portfolio] {$sujet}"
            : "[Portfolio] Nouveau message de {$nom}";
        $mail->Subject = $sujetMail;

        // Corps HTML
        $msgHtml = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $date    = date('d/m/Y à H:i');

        $mail->isHTML(true);
        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f7f5f0;font-family:'DM Sans',Arial,sans-serif;">
  <div style="max-width:560px;margin:2rem auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
    <div style="background:#d4550a;padding:1.5rem 2rem;">
      <span style="font-size:1.8rem;font-weight:800;color:#fff;letter-spacing:-.03em;">F.</span>
      <span style="color:rgba(255,255,255,.8);font-size:.9rem;margin-left:.75rem;">Nouveau message — Portfolio</span>
    </div>
    <div style="padding:2rem;">
      <table style="width:100%;border-collapse:collapse;margin-bottom:1.5rem;">
        <tr>
          <td style="padding:.5rem 0;font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6b6659;width:80px;">Nom</td>
          <td style="padding:.5rem 0;font-size:.95rem;color:#18160f;">{$nom}</td>
        </tr>
        <tr>
          <td style="padding:.5rem 0;font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6b6659;">Email</td>
          <td style="padding:.5rem 0;font-size:.95rem;"><a href="mailto:{$email}" style="color:#d4550a;text-decoration:none;">{$email}</a></td>
        </tr>
        <tr>
          <td style="padding:.5rem 0;font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6b6659;">Sujet</td>
          <td style="padding:.5rem 0;font-size:.95rem;color:#18160f;">{$sujet}</td>
        </tr>
        <tr>
          <td style="padding:.5rem 0;font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6b6659;">Date</td>
          <td style="padding:.5rem 0;font-size:.95rem;color:#18160f;">{$date}</td>
        </tr>
      </table>

      <div style="background:#f7f5f0;border-left:3px solid #d4550a;border-radius:0 8px 8px 0;padding:1.25rem 1.5rem;font-size:.95rem;line-height:1.75;color:#18160f;">
        {$msgHtml}
      </div>

      <div style="margin-top:1.5rem;text-align:center;">
        <a href="mailto:{$email}?subject=Re: {$sujet}"
           style="display:inline-block;background:#d4550a;color:#fff;text-decoration:none;padding:.65rem 1.5rem;border-radius:8px;font-weight:600;font-size:.93rem;">
          Répondre à {$nom} →
        </a>
      </div>
    </div>
    <div style="padding:1rem 2rem;background:#eeebd4;border-top:1px solid #e0ddd5;font-size:.78rem;color:#6b6659;text-align:center;">
      Message #{$messageId} • IP : {$ip} • Portfolio Florian DC
    </div>
  </div>
</body>
</html>
HTML;

        // Corps texte (fallback)
        $mail->AltBody = "Nouveau message de {$nom} ({$email})\n\nSujet : {$sujet}\n\n{$message}\n\n---\nMessage #{$messageId} • {$date}";

        $mail->send();
        $mailerOk = true;

    } catch (MailException $e) {
        $mailerError = $mail->ErrorInfo;
        error_log("[contact] PHPMailer #{$messageId}: {$mailerError}");
    }

} else {
    // --- Fallback : mail() natif (moins fiable) ---
    $sujetMail = $sujet
        ? "[Portfolio] {$sujet}"
        : "[Portfolio] Nouveau message de {$nom}";

    $corps  = "Nouveau message de {$nom} ({$email})\n";
    $corps .= "Sujet : {$sujet}\n\n{$message}\n\n";
    $corps .= "---\nMessage #{$messageId} • IP : {$ip}";

    $headers  = "From: " . SMTP_FROM . "\r\n";
    $headers .= "Reply-To: {$email}\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";

    $mailerOk = mail(MAIL_TO, $sujetMail, $corps, $headers);

    if (!$mailerOk) {
        error_log("[contact] mail() fallback failed #{$messageId}. Installez PHPMailer.");
    }
}

// ── 9. Rate limit : enregistrer la requête ───
$_SESSION['contact_times'][] = $now;

// ── 10. Réponse ──────────────────────────────
// Le message est TOUJOURS sauvegardé en BDD même si l'email échoue.
// L'expéditeur reçoit un succès dans les deux cas (son message est bien reçu).
echo json_encode([
    'ok'      => true,
    'message' => 'Votre message a bien été envoyé ! Je vous répondrai dès que possible.',
    'id'      => $messageId,
]);
