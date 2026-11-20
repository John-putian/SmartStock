<?php
/*
 * EXAMPLE FILE - safe to commit.
 *
 * Copy this to database.php and set the values for your machine. On a default
 * XAMPP install the user is 'root' with an empty password; on the shop's LAN
 * server it is a dedicated account with a real password, which is why this
 * file is in .gitignore.
 */

/**
 * Database connection settings.
 * Default values match a standard XAMPP installation
 * (MySQL on localhost, user "root", blank password).
 */
return [
    'host'     => '127.0.0.1',
    'port'     => '3306',
    'database' => 'aa_inventory',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
];
