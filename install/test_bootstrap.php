<?php
/* This file is part of a copyrighted work; it is distributed with NO WARRANTY.
 * See the file COPYRIGHT.html for more details.
 */

/**
 * Bootstrap test page for migration system
 * Access via: http://localhost:8989/install/test_bootstrap.php
 * 
 * This page demonstrates the bootstrap process for existing 0.8.1 installations
 */

$doing_install = true;
$tab = 'admin';
require_once("../shared/common.php");
require_once("../database_constants.php");
require_once("../classes/MigrationRunner.php");

include("../install/header.php");
?>

<h1>Migration System Bootstrap Test</h1>

<div style="font-family: monospace; background: #f5f5f5; padding: 20px; border: 1px solid #ddd;">

<?php
try {
    // Create migration runner
    $runner = new MigrationRunner('../migrations');
    
    echo "<h2>Pre-Bootstrap Status</h2>";
    
    // Check current state
    $tableExists = $runner->schemaMigrationsTableExists();
    echo "<p><strong>Schema migrations table exists:</strong> " . ($tableExists ? "YES" : "NO") . "</p>";
    
    $currentVersion = $runner->getCurrentDatabaseVersion();
    echo "<p><strong>Current database version:</strong> " . H($currentVersion) . "</p>";
    echo "<p><strong>Latest version:</strong> " . H(OBIB_LATEST_DB_VERSION) . "</p>";
    
    $discovered = $runner->discoverMigrations();
    echo "<p><strong>Discovered migrations:</strong> " . count($discovered) . "</p>";
    
    $applied = $runner->getAppliedMigrations();
    echo "<p><strong>Applied migrations:</strong> " . count($applied) . "</p>";
    
    $pending = $runner->getPendingMigrations();
    echo "<p><strong>Pending migrations:</strong> " . count($pending) . "</p>";
    
    echo "<hr>";
    
    // Check if we should run bootstrap
    if (!$tableExists) {
        echo "<h2>Running Bootstrap Process</h2>";
        echo "<p style='color: blue;'>⚙ Bootstrapping migration system for existing 0.8.1 installation...</p>";
        
        list($notices, $error) = $runner->bootstrap();
        
        if ($error) {
            echo "<p style='color: red; font-weight: bold;'>✗ Bootstrap FAILED</p>";
            echo "<p style='color: red;'>Error: " . H($error->toStr()) . "</p>";
        } else {
            echo "<p style='color: green; font-weight: bold;'>✓ Bootstrap SUCCESSFUL</p>";
            echo "<h3>Bootstrap Actions:</h3>";
            echo "<ul>";
            foreach ($notices as $notice) {
                echo "<li>" . H($notice) . "</li>";
            }
            echo "</ul>";
        }
        
        echo "<hr>";
        echo "<h2>Post-Bootstrap Status</h2>";
        
        // Check state after bootstrap
        $tableExists = $runner->schemaMigrationsTableExists();
        echo "<p><strong>Schema migrations table exists:</strong> " . ($tableExists ? "YES" : "NO") . "</p>";
        
        $applied = $runner->getAppliedMigrations();
        echo "<p><strong>Applied migrations:</strong> " . count($applied) . "</p>";
        
        if (!empty($applied)) {
            echo "<h3>Applied Migrations:</h3>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>Version</th><th>Description</th><th>Applied At</th><th>Checksum</th></tr>";
            foreach ($applied as $version => $info) {
                echo "<tr>";
                echo "<td>" . H($version) . "</td>";
                echo "<td>" . H($info['description']) . "</td>";
                echo "<td>" . H($info['applied_at']) . "</td>";
                echo "<td><code>" . H(substr($info['checksum'], 0, 16)) . "...</code></td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        $pending = $runner->getPendingMigrations();
        echo "<p><strong>Pending migrations:</strong> " . count($pending) . "</p>";
        
        if (!empty($pending)) {
            echo "<h3>Pending Migrations:</h3>";
            echo "<ul>";
            foreach ($pending as $version => $info) {
                echo "<li>" . H($version) . ": " . H($info['filename']) . "</li>";
            }
            echo "</ul>";
        }
        
        echo "<hr>";
        echo "<p style='color: green; font-weight: bold;'>✓ Bootstrap test completed successfully!</p>";
        echo "<p>The migration system is now ready to run pending migrations.</p>";
        
    } else {
        echo "<h2>Bootstrap Already Complete</h2>";
        echo "<p style='color: orange;'>⚠ The schema_migrations table already exists.</p>";
        echo "<p>Bootstrap has already been run. Current state:</p>";
        
        if (!empty($applied)) {
            echo "<h3>Applied Migrations:</h3>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>Version</th><th>Description</th><th>Applied At</th></tr>";
            foreach ($applied as $version => $info) {
                echo "<tr>";
                echo "<td>" . H($version) . "</td>";
                echo "<td>" . H($info['description']) . "</td>";
                echo "<td>" . H($info['applied_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        if (!empty($pending)) {
            echo "<h3>Pending Migrations:</h3>";
            echo "<ul>";
            foreach ($pending as $version => $info) {
                echo "<li>" . H($version) . ": " . H($info['filename']) . "</li>";
            }
            echo "</ul>";
            echo "<p><a href='test_run_migrations.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>→ Run Pending Migrations</a></p>";
        } else {
            echo "<p style='color: green;'>✓ All migrations are up to date!</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>✗ ERROR: " . H($e->getMessage()) . "</p>";
    echo "<pre>" . H($e->getTraceAsString()) . "</pre>";
}
?>

</div>

<p>
    <a href="test_migrations_web.php">← Back to Migration Test</a> | 
    <a href="../install/index.php">← Back to Install/Upgrade</a>
</p>

<?php include("../install/footer.php"); ?>
