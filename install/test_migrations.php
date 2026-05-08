<?php
/* This file is part of a copyrighted work; it is distributed with NO WARRANTY.
 * See the file COPYRIGHT.html for more details.
 */

/**
 * Test script for migration system
 * Run this from command line: 
 *   docker-compose exec app php /var/www/html/install/test_migrations.php
 * Or from project root:
 *   php install/test_migrations.php
 */

// Set up paths based on where script is run from
$scriptDir = dirname(__FILE__);
$rootDir = dirname($scriptDir);

$doing_install = true;
require_once($rootDir . "/shared/common.php");
require_once($rootDir . "/classes/MigrationRunner.php");

echo "=== Migration System Test ===\n\n";

// Create migration runner
$runner = new MigrationRunner($rootDir . '/migrations');

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

echo "=== Test Complete ===\n";
