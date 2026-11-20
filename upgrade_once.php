<?php
/**
 * This one-time upgrade script has been used and is now permanently disabled.
 * The shared-database upgrade is applied; nothing here needs to run again.
 * The script itself lives on at database/upgrade_shared.sql if it is ever
 * needed on another machine - it is safe to re-run.
 */
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
exit("Gone. This upgrade script has already been used and is disabled.\n");
