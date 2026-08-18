<?php
// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>\n";
echo "Starting Database Fix Script...\n";

// Determine if we are in public folder or root folder
$basePath = __DIR__;
if (file_exists($basePath . '/../vendor/autoload.php')) {
    $basePath = realpath($basePath . '/..');
} elseif (!file_exists($basePath . '/vendor/autoload.php')) {
    die("Error: Cannot find vendor/autoload.php. Make sure this script is in the root directory or public directory of your Laravel project.\n");
}

require $basePath . '/vendor/autoload.php';
$app = require_once $basePath . '/bootstrap/app.php';

// Bootstrap Laravel depending on environment (Web or Console)
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    $tables = DB::select('SHOW TABLES');
    echo "Successfully connected to the database.\n\n";
} catch (\Exception $e) {
    die("Database Connection Failed: " . $e->getMessage() . "\n");
}

foreach ($tables as $tableInfo) {
    $tableName = array_values((array)$tableInfo)[0];
    
    // Process tables that have an 'id' column
    if (Schema::hasColumn($tableName, 'id')) {
        echo "Processing table: $tableName...\n";
        
        try {
            DB::statement("ALTER TABLE `$tableName` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");
            echo "  [SUCCESS] Updated $tableName (BIGINT).\n";
        } catch (\Exception $e) {
            // If BIGINT fails, it might be an INT column
            try {
                DB::statement("ALTER TABLE `$tableName` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");
                echo "  [SUCCESS] Updated $tableName (INT).\n";
            } catch (\Exception $e2) {
                // If it fails because the column is a string (like job_batches sometimes), skip it
                echo "  [FAILED] Could not modify $tableName. It might not be a standard auto-incrementing integer column.\n";
            }
        }
    }
}

// Fix common tables that use string primary keys (if they lost them)
$stringPrimaryKeys = [
    'cache' => 'key',
    'cache_locks' => 'key',
    'job_batches' => 'id'
];

echo "\nChecking tables with string primary keys...\n";
foreach ($stringPrimaryKeys as $tableName => $columnName) {
    if (Schema::hasTable($tableName)) {
        try {
            // Attempt to add primary key (will fail if it already exists, which is fine)
            DB::statement("ALTER TABLE `$tableName` ADD PRIMARY KEY (`$columnName`)");
            echo "  [SUCCESS] Added Primary Key to $tableName.\n";
        } catch (\Exception $e) {
            echo "  [SKIPPED] $tableName already has a primary key or failed.\n";
        }
    }
}

echo "\nAll Done!</pre>\n";
