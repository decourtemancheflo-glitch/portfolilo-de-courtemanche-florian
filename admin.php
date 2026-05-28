<?php
// =============================================
// admin.php — Panneau de lecture des messages
// Accès : votre-site.fr/admin.php
// =============================================

require_once __DIR__ . '/config.php';
session_start();

// ── Authentification ─────────────────────────
$loginError = '';

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (!isset($_SESSION['admin_ok'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';

        if ($user === ADMIN_USER && password_verify($pass, ADMIN_PASS)) {
            session_regenerate_id(true);
            $_SESSION['admin_ok'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $loginError = 'Identifiants incorrects.';
            sleep(1); // Ralentir les tentatives brute-force
        }
    }

    // Afficher le formulaire de connexion
    ?>
    <!doctype html>
    <html lang="fr">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Admin — Florian DC</title>
      <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
          font-family: system-ui, sans-serif;
          background: #0f0e0c;
          color: #f2ede5;
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .login-box {
          background: #1c1a16;
          border: 1px solid #2c2a24;
          border-radius: 16px;
          padding: 2.5rem;
          width: min(400px, 90vw);
        }
        .login-box h1 {
          font-size: 1.4rem;
          margin-bottom: .25rem;
          letter-spacing: -.02em;
        }
        .login-box p { color: #938e84; font-size: .9rem; margin-bottom: 2rem; }
        label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .4rem; color: #f2ede5; }
        input {
          width: 100%; padding: .7rem 1rem;
          background: #111010; border: 1.5px solid #2c2a24;
          border-radius: 8px; color: #f2ede5; font: inherit;
          margin-bottom: 1.1rem;
          transition: border-color .2s;
        }
        input:focus { outline: none; border-color: #f07030; }
        button {
          width: 100%; padding: .75rem;
          background: #f07030; color: #fff;
          border: none; border-radius: 8px;
          font: inherit; font-weight: 600; cursor: pointer;
          transition: background .15s;
        }
        button:hover { background: #d45e20; }
        .error {
          background: rgba(217,48,48,.15); border: 1px solid #d93030;
          color: #f87171; padding: .75rem 1rem; border-radius: 8px;
          font-size: .88rem; margin-bottom: 1.25rem;
        }
        .logo { font-size: 2rem; font-weight: 800; letter-spacing: -.03em; margin-bottom: 1rem; }
        .logo span { color: #f07030; }
      </style>
    </head>
    <body>
      <div class="login-box">
        <div class="logo">F<span>.</span></div>
        <h1>Panneau admin</h1>
        <p>Connectez-vous pour lire vos messages.</p>
        <?php if ($loginError): ?>
          <div class="error"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="POST">
          <label for="username">Nom d'utilisateur</label>
          <input id="username" name="username" type="text" autocomplete="username" required>
          <label for="password">Mot de passe</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required>
          <button type="submit">Se connecter →</button>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// ── Connecté : accès aux données ─────────────
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données.');
}

// Marquer un message comme lu
if (isset($_GET['lu']) && is_numeric($_GET['lu'])) {
    $pdo->prepare('UPDATE messages SET lu = 1 WHERE id = ?')->execute([(int)$_GET['lu']]);
    header('Location: admin.php');
    exit;
}

// Supprimer un message
if (isset($_GET['suppr']) && is_numeric($_GET['suppr'])) {
    $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([(int)$_GET['suppr']]);
    header('Location: admin.php');
    exit;
}

// Pagination
$perPage = 20;
$page    = max(1, (int)($_GET['p'] ?? 1));
$offset  = ($page - 1) * $perPage;

$total   = (int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
$nonLus  = (int)$pdo->query('SELECT COUNT(*) FROM messages WHERE lu = 0')->fetchColumn();
$pages   = (int)ceil($total / $perPage);

$messages = $pdo->prepare(
    'SELECT * FROM messages ORDER BY cree_le DESC LIMIT :limit OFFSET :offset'
);
$messages->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$messages->bindValue(':offset', $offset,  PDO::PARAM_INT);
$messages->execute();
$rows = $messages->fetchAll();

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Messages (<?= $nonLus ?> non lus) — Admin Florian DC</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, sans-serif; background: #0f0e0c; color: #f2ede5; min-height: 100vh; }

    /* Header */
    .admin-header {
      position: sticky; top: 0; z-index: 10;
      background: #1c1a16; border-bottom: 1px solid #2c2a24;
      padding: 1rem 2rem; display: flex; align-items: center;
      justify-content: space-between; gap: 1rem;
    }
    .admin-logo { font-size: 1.4rem; font-weight: 800; letter-spacing: -.02em; }
    .admin-logo span { color: #f07030; }
    .badge {
      background: #f07030; color: #fff; font-size: .75rem; font-weight: 700;
      padding: .2rem .55rem; border-radius: 99px; margin-left: .5rem;
    }
    .logout-btn {
      background: #2c2a24; border: 1px solid #4a4740; color: #f2ede5;
      padding: .45rem 1rem; border-radius: 8px; font: inherit; font-size: .88rem;
      cursor: pointer; transition: background .15s; text-decoration: none;
    }
    .logout-btn:hover { background: #3a3834; }

    /* Main */
    .admin-main { max-width: 960px; margin: 0 auto; padding: 2rem 1.5rem; }
    .stats { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
    .stat-card {
      background: #1c1a16; border: 1px solid #2c2a24; border-radius: 12px;
      padding: 1rem 1.5rem; flex: 1; min-width: 140px;
    }
    .stat-card strong { display: block; font-size: 2rem; font-weight: 800; color: #f07030; letter-spacing: -.03em; }
    .stat-card span { font-size: .85rem; color: #938e84; }

    /* Messages */
    .msg-list { display: flex; flex-direction: column; gap: .75rem; }
    .msg-card {
      background: #1c1a16; border: 1px solid #2c2a24; border-radius: 12px;
      padding: 1.25rem 1.5rem; transition: border-color .15s;
    }
    .msg-card.unread { border-left: 3px solid #f07030; }
    .msg-card:hover { border-color: #4a4740; }

    .msg-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: .75rem; }
    .msg-from strong { font-size: 1rem; }
    .msg-from a { color: #f07030; font-size: .88rem; text-decoration: none; }
    .msg-from a:hover { text-decoration: underline; }
    .msg-meta { font-size: .8rem; color: #938e84; margin-top: .15rem; }
    .msg-subject { font-size: .88rem; font-weight: 600; color: #e8a44a; margin-bottom: .5rem; }
    .msg-body { font-size: .92rem; color: #c8c4bc; line-height: 1.7; white-space: pre-wrap; word-break: break-word; }

    .msg-actions { display: flex; gap: .5rem; flex-shrink: 0; }
    .action-btn {
      font: inherit; font-size: .8rem; padding: .35rem .8rem;
      border-radius: 6px; cursor: pointer; border: 1px solid;
      text-decoration: none; transition: background .15s, color .15s;
    }
    .btn-lu { background: transparent; border-color: #4a4740; color: #938e84; }
    .btn-lu:hover { background: #2c2a24; color: #f2ede5; }
    .btn-suppr { background: transparent; border-color: rgba(217,48,48,.4); color: #f87171; }
    .btn-suppr:hover { background: rgba(217,48,48,.15); }
    .unread-dot {
      display: inline-block; width: 8px; height: 8px;
      background: #f07030; border-radius: 50%; margin-right: .5rem; flex-shrink: 0;
      margin-top: .35rem;
    }

    /* Pagination */
    .pagination { display: flex; gap: .5rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap; }
    .pagination a, .pagination span {
      padding: .45rem .9rem; border-radius: 8px; font-size: .88rem; text-decoration: none;
      border: 1px solid #2c2a24; color: #f2ede5;
    }
    .pagination a:hover { background: #2c2a24; }
    .pagination .current { background: #f07030; border-color: #f07030; color: #fff; font-weight: 600; }

    .empty { text-align: center; padding: 4rem 2rem; color: #938e84; font-size: 1.1rem; }
  </style>
</head>
<body>

<header class="admin-header">
  <div>
    <span class="admin-logo">F<span>.</span> Admin</span>
    <?php if ($nonLus > 0): ?>
      <span class="badge"><?= $nonLus ?> non lu<?= $nonLus > 1 ? 's' : '' ?></span>
    <?php endif; ?>
  </div>
  <form method="POST" style="display:inline">
    <button class="logout-btn" name="logout" value="1">Se déconnecter</button>
  </form>
</header>

<main class="admin-main">

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <strong><?= $total ?></strong>
      <span>Message<?= $total > 1 ? 's' : '' ?> total</span>
    </div>
    <div class="stat-card">
      <strong><?= $nonLus ?></strong>
      <span>Non lu<?= $nonLus > 1 ? 's' : '' ?></span>
    </div>
    <div class="stat-card">
      <strong><?= $total - $nonLus ?></strong>
      <span>Lu<?= ($total - $nonLus) > 1 ? 's' : '' ?></span>
    </div>
  </div>

  <!-- Liste des messages -->
  <?php if (empty($rows)): ?>
    <p class="empty">📭 Aucun message pour l'instant.</p>
  <?php else: ?>
    <div class="msg-list">
      <?php foreach ($rows as $row): ?>
        <div class="msg-card <?= $row['lu'] ? '' : 'unread' ?>">
          <div class="msg-header">
            <div class="msg-from">
              <?php if (!$row['lu']): ?><span class="unread-dot"></span><?php endif; ?>
              <strong><?= e($row['nom']) ?></strong>
              <a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a>
              <div class="msg-meta">
                #<?= $row['id'] ?> •
                <?= date('d/m/Y à H:i', strtotime($row['cree_le'])) ?>
                <?php if ($row['ip']): ?> • IP : <?= e($row['ip']) ?><?php endif; ?>
              </div>
            </div>
            <div class="msg-actions">
              <?php if (!$row['lu']): ?>
                <a href="?lu=<?= $row['id'] ?>" class="action-btn btn-lu">✓ Marquer lu</a>
              <?php endif; ?>
              <a href="?suppr=<?= $row['id'] ?>" class="action-btn btn-suppr"
                 onclick="return confirm('Supprimer ce message ?')">🗑 Supprimer</a>
            </div>
          </div>
          <?php if (!empty($row['sujet'])): ?>
            <div class="msg-subject">📌 <?= e($row['sujet']) ?></div>
          <?php endif; ?>
          <div class="msg-body"><?= e($row['message']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?p=<?= $page - 1 ?>">‹ Précédent</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <?php if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
          <?php else: ?>
            <a href="?p=<?= $i ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
          <a href="?p=<?= $page + 1 ?>">Suivant ›</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

</main>

</body>
</html>
