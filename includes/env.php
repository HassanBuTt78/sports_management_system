<?php
/**
 * ============================================================
 * env.php  —  NOT part of the database, NOT sent to the browser
 * ------------------------------------------------------------
 * Holds the AES-256-GCM key used to encrypt the admin-recoverable
 * copy of coach/player passwords (see encryptCredential() /
 * decryptCredential() in functions.php). This is deliberately a
 * plain PHP file rather than a database row: the whole point of
 * "the key must not be stored in the database" is that a DB dump
 * or SQL-injection leak alone can never decrypt the credentials.
 *
 * IMPORTANT — before deploying this for real:
 *   1. Generate your OWN key (don't reuse the one below):
 *        php -r "echo base64_encode(random_bytes(32));"
 *   2. Replace CREDENTIAL_ENC_KEY's value with that output.
 *   3. Make sure this file is NOT web-accessible (the .htaccess
 *      in this same folder already denies direct requests to
 *      *.php inside includes/ on Apache — confirm mod_rewrite/
 *      mod_authz_core is enabled) and NOT committed to a public
 *      git repo (add includes/env.php to .gitignore).
 *   4. If this key is ever lost or rotated, every existing
 *      encrypted_password value becomes permanently undecryptable
 *      — the bcrypt `password` column (used for actual login) is
 *      completely unaffected either way, so logins keep working;
 *      only Admin's "Show Password" reveal would need a fresh
 *      Reset Password for each account after a key rotation.
 * ============================================================
 */

define('CREDENTIAL_ENC_KEY', 's4o2Gg+wrxonrvIGxEsCPLxxmF8Gls2bgYg1Lln+V1U=');
