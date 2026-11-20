<?php
/**
 * Photo suggestion settings.
 *
 * DO NOT PUT THE REAL KEY IN THIS FILE. This file travels with the project,
 * so a key written here ends up in every copy and every zip you hand in.
 *
 * Put it in ONE of these instead:
 *   1. config/photo-ai.local.php   - copy photo-ai.local.example.php and fill
 *                                    it in. That file stays on this machine:
 *                                    it is excluded from the delivered zip and
 *                                    config/.htaccess denies it to the web.
 *   2. An environment variable     - SMARTSTOCK_GEMINI_API_KEY on the server.
 *
 * Either way the key stays server-side. It is never sent to the browser.
 */
$config = [
    'api_key' => getenv('SMARTSTOCK_GEMINI_API_KEY') ?: '',
    // Model name as published in Google's Interactions API quickstart. Override
    // with SMARTSTOCK_AI_MODEL if your key is enabled for a different one.
    'model' => getenv('SMARTSTOCK_AI_MODEL') ?: 'gemini-3.5-flash',
    // Pins the request to a published API revision so a later change to the
    // service does not silently alter the response shape this code reads.
    'api_revision' => getenv('SMARTSTOCK_AI_REVISION') ?: '2026-05-20',
    // Where the request goes. Settable so the failure handling can be tested
    // against a service that really does return an error or refuse the
    // connection, instead of trusting that those branches work. Leave it alone
    // in normal use; it must be https.
    'endpoint' => getenv('SMARTSTOCK_AI_ENDPOINT')
        ?: 'https://generativelanguage.googleapis.com/v1beta/interactions',
    // Explicit demo fallback, not a market valuation. Disable for real store pricing.
    'demo_pricing' => getenv('SMARTSTOCK_DEMO_PRICING') !== '0',
    'demo_price' => 500.00,
];

// Local, unshared overrides win over everything above.
$local = __DIR__ . '/photo-ai.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace($config, $override);
    }
}

return $config;
