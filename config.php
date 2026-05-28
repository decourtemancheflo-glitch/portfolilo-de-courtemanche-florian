<?php
// =============================================
// config.php — Configuration
// ⚠️  Ne jamais committer ce fichier sur Git !
// =============================================

// --- Identifiants MySQL ---
define('DB_HOST',    'localhost');
define('DB_NAME',    'portfolio_fdc');
define('DB_USER',    'root');       // ← Votre utilisateur MySQL
define('DB_PASS',    '');           // ← Votre mot de passe MySQL
define('DB_CHARSET', 'utf8mb4');

// --- SMTP Brevo ---
// 1. Créez un compte gratuit sur https://app.brevo.com
// 2. Allez dans : Mon compte → SMTP & API → Onglet SMTP
// 3. Copiez le Login et le mot de passe SMTP ici
define('SMTP_HOST',     'smtp-relay.brevo.com');
define('SMTP_PORT',     587);
define('SMTP_USER',     'decourtemancheflo@gmail.com'); // ← votre login Brevo (votre email d'inscription)
define('SMTP_PASS',     'VOTRE_MOT_DE_PASSE_SMTP_BREVO'); // ← mot de passe SMTP Brevo (pas votre mdp Brevo !)
define('SMTP_FROM',     'decourtemancheflo@gmail.com');
define('SMTP_FROM_NAME','Florian DC — Portfolio');

// --- Email de destination ---
define('MAIL_TO',      'decourtemancheflo@gmail.com');
define('MAIL_TO_NAME', 'Florian De Courtemanche');

// --- Sécurité admin ---
define('ADMIN_USER', 'florian');
// Générez ce hash avec : php -r "echo password_hash('votre-mot-de-passe', PASSWORD_DEFAULT);"
define('ADMIN_PASS', password_hash('changez-ce-mot-de-passe', PASSWORD_DEFAULT));

// --- Environnement ---
define('ENV', 'production');
if (ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}