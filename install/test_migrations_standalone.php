<?php
/* This file is part of a copyrighted work; it is distributed with NO WARRANTY.
 * See the file COPYRIGHT.html for more details.
 */

/**
 * Standalone test script for migration system
 * Run from Docker: docker-compose exec app php /var/www/html/install/test_migrations_standalone.php
 */

// Change to project root directory
chdir(dirname(__FILE__) . '/..');

// Load dependencies in correct order
require_once("./database_constants.php");
require_once("./shared/global_constants.php");
require_once("./classes/Query.php");
require_once("./classes/InstallQuery.php");
require_once("./classes/Migration.php");
require_once("./classes/MigrationRunner.php");

echo "=== Migration System Test (Standalone) ===\n\n";
echo "Database: " . DB_NAME . "\n";
echo "Table Prefix: " . DB_TABLENAME_PREFIX . "\n\n";

try {
    // Create migration runner
    $runner = new MigrationRunner('./migrations');
    
    // Test 1: Check if schema_migrations table exists
    echo "Test 1: Checking for schema_migrations table...\n";
    $exists = $runner->schemaMigrationsTableExists();
    echo "  Result: " . ($exists ? "EXISTS" : "DOES NOT EXIST") . "\n\n";
    
    // Test 2: Discover migrations
    echo "Test 2: Discovering migration files...\n";
    $discovered = $runner->discoverMigrations();
    echo "  Found " . count($discovered) . " migration(s):\n";
    foreach ($discovered as $version => $info) {
        echo "    - $version: {$info['filename']}\n";
    }
    echo "\n";
    
    // Test 3: Check for sequence gaps
    echo "Test 3: Checking for sequence gaps...\n";
    $gaps = $runner->checkSequenceGaps();
    if (empty($gaps)) {
        echo "  No gaps found.\n";
    } else {
        foreach ($gaps as $gap) {
            echo "  WARNING: $gap\n";
        }
    }
    echo "\n";
    
    // Test 4: Get applied migrations
    echo "Test 4: Getting applied migrations...\n";
    $applied = $runner->getAppliedMigrations();
    echo "  Found " . count($applied) . " applied migration(s):\n";
    foreach ($applied as $version => $info) {
        echo "    - $version: {$info['description']} (applied: {$info['applied_at']})\n";
    }
    echo "\n";
    
    // Test 5: Get pending migrations
    echo "Test 5: Getting pending migrations...\n";
    $pending = $runner->getPendingMigrations();
    echo "  Found " . count($pending) . " pending migration(s):\n";
    foreach ($pending as $version => $info) {
        echo "    - $version: {$info['filename']}\n";
    }
    echo "\n";
    
    // Test 6: Validate checksums
    echo "Test 6: Validating checksums...\n";
    $warnings = $runner->validateChecksums();
    if (empty($warnings)) {
        echo "  All checksums valid.\n";
    } else {
        foreach ($warnings as $version => $warning) {
            echo "  WARNING: $warning\n";
        }
    }
    echo "\n";
    
    // Test 7: Calculate checksum for a migration
    if (!empty($discovered)) {
        $firstMigration = reset($discovered);
        echo "Test 7: Calculating checksum for {$firstMigration['filename']}...\n";
        $checksum = $runner->calculateChecksum($firstMigration['filepath']);
        echo "  Checksum: $checksum\n\n";
    }
    
    // Test 8: Check current database version
    echo "Test 8: Checking current database version...\n";
    $currentVersion = $runner->getCurrentDatabaseVersion();
    if ($currentVersion === false) {
        echo "  No database found (fresh install)\n";
    } else {
        echo "  Current version: $currentVersion\n";
        echo "  Latest version: " . OBIB_LATEST_DB_VERSION . "\n";
        if ($currentVersion === OBIB_LATEST_DB_VERSION) {
            echo "  Status: UP TO DATE\n";
        } else {
            echo "  Status: UPGRADE NEEDED\n";
        }
    }
    echo "\n";
    
    echo "=== Test Complete ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
