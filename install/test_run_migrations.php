<?php
/* This file is part of a copyrighted work; it is distributed with NO WARRANTY.
 * See the file COPYRIGHT.html for more details.
 */

/**
 * Run pending migrations test page
 * Access via: http://localhost:8989/install/test_run_migrations.php
 * 
 * This page runs all pending migrations
 */

$doing_install = true;
$tab = 'admin';
require_once("../shared/common.php");
require_once("../database_constants.php");
require_once("../classes/MigrationRunner.php");

include("../install/header.php");
?>

<h1>Run Pending Migrations</h1>

<div style="font-family: monospace; background: #f5f5f5; padding: 20px; border: 1px solid #ddd;">

<?php
try {
    // Create migration runner
    $runner = new MigrationRunner('../migrations');
    
    echo "<h2>Pre-Migration Status</h2>";
    
    $tableExists = $runner->schemaMigrationsTableExists();
    if (!$tableExists) {
        echo "<p style='color: red;'>✗ Schema migrations table does not exist!</p>";
        echo "<p>Please run the <a href='test_bootstrap.php'>bootstrap process</a> first.</p>";
    } else {
        $applied = $runner->getAppliedMigrations();
        $pending = $runner->getPendingMigrations();
        
        echo "<p><strong>Applied migrations:</strong> " . count($applied) . "</p>";
        echo "<p><strong>Pending migrations:</strong> " . count($pending) . "</p>";
        
        if (empty($pending)) {
            echo "<p style='color: green;'>✓ No pending migrations. Database is up to date!</p>";
        } else {
            echo "<h3>Pending Migrations to Run:</h3>";
            echo "<ul>";
            foreach ($pending as $version => $info) {
                echo "<li>" . H($version) . ": " . H($info['filename']) . "</li>";
            }
            echo "</ul>";
            
            echo "<hr>";
            echo "<h2>Running Migrations</h2>";
            
            list($notices, $error) = $runner->runPendingMigrations();
            
            if ($error) {
                echo "<p style='color: red; font-weight: bold;'>✗ Migration FAILED</p>";
                echo "<p style='color: red;'>Error: " . H($error->toStr()) . "</p>";
            } else {
                echo "<p style='color: green; font-weight: bold;'>✓ Migrations SUCCESSFUL</p>";
                echo "<h3>Migration Results:</h3>";
                echo "<ul>";
                foreach ($notices as $notice) {
                    $style = '';
                    if (strpos($notice, 'WARNING') !== false) {
                        $style = ' style="color: orange;"';
                    } elseif (strpos($notice, 'applied') !== false) {
                        $style = ' style="color: green;"';
                    }
                    echo "<li$style>" . H($notice) . "</li>";
                }
                echo "</ul>";
            }
            
            echo "<hr>";
            echo "<h2>Post-Migration Status</h2>";
            
            $applied = $runner->getAppliedMigrations();
            $pending = $runner->getPendingMigrations();
            
            echo "<p><strong>Applied migrations:</strong> " . count($applied) . "</p>";
            echo "<p><strong>Pending migrations:</strong> " . count($pending) . "</p>";
            
            if (!empty($applied)) {
                echo "<h3>All Applied Migrations:</h3>";
                echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
                echo "<tr><th>Version</th><th>Description</th><th>Applied At</th></tr>";
                foreach ($applied as $version => $info) {
                    // Highlight newly applied migrations
                    $isNew = isset($pending[$version]);
                    $rowStyle = $isNew ? " style='background-color: #d4edda;'" : "";
                    
                    echo "<tr$rowStyle>";
                    echo "<td>" . H($version) . "</td>";
                    echo "<td>" . H($info['description']) . "</td>";
                    echo "<td>" . H($info['applied_at']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            
            if (empty($pending)) {
                echo "<p style='color: green; font-weight: bold;'>✓ All migrations complete! Database is up to date.</p>";
            }
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
    <a href="test_bootstrap.php">← Back to Bootstrap</a> | 
    <a href="../install/index.php">← Back to Install/Upgrade</a>
</p>

<?php include("../install/footer.php"); ?>
