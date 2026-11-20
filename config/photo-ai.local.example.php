<?php
/*
 * EXAMPLE FILE - safe to commit.
 *
 * Copy this to photo-ai.local.php and paste your own Gemini API key, from
 * aistudio.google.com/apikey. photo-ai.local.php is in .gitignore.
 */

/**
 * LOCAL SETTINGS — this machine only.
 *
 * 1. Copy this file to:  config/photo-ai.local.php
 * 2. Paste your Google Gemini API key between the quotes below.
 * 3. Restart Apache in the XAMPP Control Panel (Stop, then Start).
 *
 * The copy you create is deliberately left out of the delivered zip, and
 * config/.htaccess already denies the whole config folder to the web, so the
 * key cannot be read through the browser. Never paste a key into
 * config/photo-ai.php itself, and never into a .js file.
 *
 * To turn the feature off again, delete config/photo-ai.local.php (or blank
 * the key). The system then says photo suggestions are unavailable and the
 * rest of Smart Stock carries on exactly as before.
 */
return [
    'api_key' => 'PASTE-YOUR-GEMINI-KEY-HERE',

    // Only if your key is enabled for a different model.
    // 'model' => 'gemini-3.5-flash',

    // Set to false for real trading, so an unmatched item asks for a price
    // instead of offering the 500-peso demo placeholder.
    // 'demo_pricing' => true,
];
