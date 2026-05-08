<?php
/* This file is part of a copyrighted work; it is distributed with NO WARRANTY.
 * See the file COPYRIGHT.html for more details.
 */

/**
 * Web-accessible test page for migration system
 * Access via: http://localhost:8989/install/test_migrations_web.php
 */

$doing_install = true;
$tab = 'admin';
require_once("../shared/common.php");
require_once("../database_constants.php");
require_once("../classes/MigrationRunner.php");

include("../install/header.php");
?>

<h1>Migration System Test</h1>

<div style="font-family: monospace; background: #f5f5f5; padding: 20px; border: 1px solid #ddd;">

<?php
try {
    if (defined('DB_NAME')) {
        echo "<p><strong>Database:</strong> " . H(DB_NAME) . "</p>";
    }
    if (defined('DB_TABLENAME_PREFIX')) {
        echo "<p><strong>Table Prefix:</strong> " . H(DB_TABLENAME_PREFIX) . "</p>";
    }
    echo "<hr>";
    
    // Create migration runner
    $runner = new MigrationRunner('../migrations');
    
    // Test 1: Check if schema_migrations table exists
    echo "<h3>Test 1: Schema Migrations Table</h3>";
    $exists = $runner->schemaMigrationsTableExists();
    echo "<p>Status: <strong>" . ($exists ? "EXISTS" : "DOES NOT EXIST") . "</strong></p>";
    
    // Test 2: Discover migrations
    echo "<h3>Test 2: Discovered Migration Files</h3>";
    $discovered = $runner->discoverMigrations();
    echo "<p>Found <strong>" . count($discovered) . "</strong> migration(s):</p>";
    if (!empty($discovered)) {
        echo "<ul>";
        foreach ($discovered as $version => $info) {
            echo "<li>" . H($version) . ": " . H($info['filename']) . "</li>";
        }
        echo "</ul>";
    }
    
    // Test 3: Check for sequence gaps
    echo "<h3>Test 3: Sequence Gap Check</h3>";
    $gaps = $runner->checkSequenceGaps();
    if (empty($gaps)) {
        echo "<p style='color: green;'>✓ No gaps found</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Warnings:</p><ul>";
        foreach ($gaps as $gap) {
            echo "<li>" . H($gap) . "</li>";
        }
        echo "</ul>";
    }
    
    // Test 4: Get applied migrations
    echo "<h3>Test 4: Applied Migrations</h3>";
    $applied = $runner->getAppliedMigrations();
    echo "<p>Found <strong>" . count($applied) . "</strong> applied migration(s):</p>";
    if (!empty($applied)) {
        echo "<ul>";
        foreach ($applied as $version => $info) {
            echo "<li>" . H($version) . ": " . H($info['description']) . 
                 " <em>(applied: " . H($info['applied_at']) . ")</em></li>";
        }
        echo "</ul>";
    }
    
    // Test 5: Get pending migrations
    echo "<h3>Test 5: Pending Migrations</h3>";
    $pending = $runner->getPendingMigrations();
    echo "<p>Found <strong>" . count($pending) . "</strong> pending migration(s):</p>";
    if (!empty($pending)) {
        echo "<ul>";
        foreach ($pending as $version => $info) {
            echo "<li>" . H($version) . ": " . H($info['filename']) . "</li>";
        }
        echo "</ul>";
    }
    
    // Test 6: Validate checksums
    echo "<h3>Test 6: Checksum Validation</h3>";
    $warnings = $runner->validateChecksums();
    if (empty($warnings)) {
        echo "<p style='color: green;'>✓ All checksums valid</p>";
    } else {
        echo "<p style='color: red;'>✗ Checksum warnings:</p><ul>";
        foreach ($warnings as $version => $warning) {
            echo "<li>" . H($warning) . "</li>";
        }
        echo "</ul>";
    }
    
    // Test 7: Calculate checksum for first migration
    if (!empty($discovered)) {
        echo "<h3>Test 7: Checksum Calculation</h3>";
        $firstMigration = reset($discovered);
        $checksum = $runner->calculateChecksum($firstMigration['filepath']);
        echo "<p>File: <strong>" . H($firstMigration['filename']) . "</strong></p>";
        echo "<p>Checksum: <code>" . H($checksum) . "</code></p>";
    }
    
    // Test 8: Check current database version
    echo "<h3>Test 8: Database Version</h3>";
    $currentVersion = $runner->getCurrentDatabaseVersion();
    if ($currentVersion === false) {
        echo "<p style='color: orange;'>No database found (fresh install)</p>";
    } else {
        echo "<p>Current version: <strong>" . H($currentVersion) . "</strong></p>";
        echo "<p>Latest version: <strong>" . H(OBIB_LATEST_DB_VERSION) . "</strong></p>";
        if ($currentVersion === OBIB_LATEST_DB_VERSION) {
            echo "<p style='color: green;'>✓ Database is UP TO DATE</p>";
        } else {
            echo "<p style='color: orange;'>⚠ UPGRADE NEEDED</p>";
        }
    }
    
    echo "<hr>";
    echo "<p style='color: green; font-weight: bold;'>✓ All tests completed successfully</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>✗ ERROR: " . H($e->getMessage()) . "</p>";
    echo "<pre>" . H($e->getTraceAsString()) . "</pre>";
}
?>

</div>

<p><a href="../install/index.php">← Back to Install/Upgrade</a></p>

<?php include("../install/footer.php"); ?>
