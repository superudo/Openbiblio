<?php
/* This file is part of a copyrighted work; it is distributed with NO WARRANTY.
 * See the file COPYRIGHT.html for more details.
 */

require_once(dirname(__FILE__) . "/InstallQuery.php");
require_once(dirname(__FILE__) . "/Migration.php");

/**
 * Manages database migrations
 * 
 * Responsibilities:
 * - Discover migration files
 * - Track which migrations have been applied
 * - Validate migration checksums
 * - Run pending migrations
 * - Bootstrap existing installations
 */
class MigrationRunner extends InstallQuery {
    
    private $migrationsDir;
    private $tablePrfx;
    
    /**
     * Constructor
     * @param string $migrationsDir Path to migrations directory
     * @param string $tablePrfx Table prefix
     */
    public function __construct($migrationsDir = '../migrations', $tablePrfx = DB_TABLENAME_PREFIX) {
        parent::__construct();
        $this->migrationsDir = $migrationsDir;
        $this->tablePrfx = $tablePrfx;
    }
    
    /**
     * Check if schema_migrations table exists
     * @return bool True if table exists
     */
    public function schemaMigrationsTableExists() {
        $sql = $this->mkSQL('SHOW TABLES LIKE %Q', $this->tablePrfx . 'schema_migrations');
        $row = $this->select01($sql);
        return $row !== NULL;
    }
    
    /**
     * Create schema_migrations table
     */
    public function createSchemaMigrationsTable() {
        $sql = "CREATE TABLE " . $this->tablePrfx . "schema_migrations (
            version VARCHAR(10) PRIMARY KEY,
            description VARCHAR(255) NOT NULL,
            applied_at DATETIME NOT NULL,
            checksum VARCHAR(64) NOT NULL
        ) ENGINE=MyISAM";
        $this->exec($sql);
    }
    
    /**
     * Get all applied migrations from database
     * @return array Array of migration records (version => [description, applied_at, checksum])
     */
    public function getAppliedMigrations() {
        if (!$this->schemaMigrationsTableExists()) {
            return array();
        }
        
        $sql = "SELECT version, description, applied_at, checksum 
                FROM " . $this->tablePrfx . "schema_migrations 
                ORDER BY version";
        $rows = $this->exec($sql);
        
        $migrations = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $migrations[$row['version']] = array(
                    'description' => $row['description'],
                    'applied_at' => $row['applied_at'],
                    'checksum' => $row['checksum']
                );
            }
        }
        return $migrations;
    }
    
    /**
     * Discover all migration files
     * @return array Array of migration info (version => [filename, filepath, class])
     */
    public function discoverMigrations() {
        $migrations = array();
        
        if (!is_dir($this->migrationsDir)) {
            return $migrations;
        }
        
        $files = scandir($this->migrationsDir);
        foreach ($files as $file) {
            // Match pattern: 001_description.php
            if (preg_match('/^(\d{3})_(.+)\.php$/', $file, $matches)) {
                $version = $matches[1];
                $filepath = $this->migrationsDir . '/' . $file;
                $className = 'Migration' . $version;
                
                $migrations[$version] = array(
                    'filename' => $file,
                    'filepath' => $filepath,
                    'class' => $className,
                    'version' => $version
                );
            }
        }
        
        // Sort by version
        ksort($migrations);
        return $migrations;
    }
    
    /**
     * Calculate checksum for a migration file
     * Normalizes line endings to prevent cross-platform issues
     * @param string $filepath Path to migration file
     * @return string MD5 checksum
     */
    public function calculateChecksum($filepath) {
        $content = file_get_contents($filepath);
        // Normalize line endings to LF to prevent cross-platform issues
        $normalized = str_replace("\r\n", "\n", $content);
        $normalized = str_replace("\r", "\n", $normalized);
        return md5($normalized);
    }
    
    /**
     * Get pending migrations (discovered but not applied)
     * @return array Array of pending migration info
     */
    public function getPendingMigrations() {
        $discovered = $this->discoverMigrations();
        $applied = $this->getAppliedMigrations();
        
        $pending = array();
        foreach ($discovered as $version => $info) {
            if (!isset($applied[$version])) {
                $pending[$version] = $info;
            }
        }
        return $pending;
    }
    
    /**
     * Validate checksums of applied migrations
     * @return array Array of warnings (version => message)
     */
    public function validateChecksums() {
        $discovered = $this->discoverMigrations();
        $applied = $this->getAppliedMigrations();
        
        $warnings = array();
        foreach ($applied as $version => $info) {
            if (isset($discovered[$version])) {
                $currentChecksum = $this->calculateChecksum($discovered[$version]['filepath']);
                if ($currentChecksum !== $info['checksum']) {
                    $warnings[$version] = "Migration $version has been modified after being applied. " .
                                         "Expected checksum: {$info['checksum']}, " .
                                         "Current checksum: $currentChecksum";
                }
            }
        }
        return $warnings;
    }
    
    /**
     * Check for gaps in migration sequence
     * @return array Array of warnings about gaps
     */
    public function checkSequenceGaps() {
        $discovered = $this->discoverMigrations();
        $versions = array_keys($discovered);
        
        if (empty($versions)) {
            return array();
        }
        
        $warnings = array();
        $expected = intval($versions[0]);
        
        foreach ($versions as $version) {
            $current = intval($version);
            if ($current != $expected) {
                $warnings[] = "Gap in migration sequence: expected " . 
                             sprintf('%03d', $expected) . ", found $version";
            }
            $expected = $current + 1;
        }
        
        return $warnings;
    }
    
    /**
     * Record a migration as applied
     * @param string $version Migration version
     * @param string $description Migration description
     * @param string $checksum Migration checksum
     */
    public function recordMigration($version, $description, $checksum) {
        $sql = $this->mkSQL(
            "INSERT INTO %I (version, description, applied_at, checksum) 
             VALUES (%Q, %Q, NOW(), %Q)",
            $this->tablePrfx . 'schema_migrations',
            $version,
            $description,
            $checksum
        );
        $this->exec($sql);
    }
    
    /**
     * Run a single migration
     * @param array $migrationInfo Migration info from discoverMigrations()
     * @return array [success, message]
     */
    public function runMigration($migrationInfo) {
        $version = $migrationInfo['version'];
        $filepath = $migrationInfo['filepath'];
        $className = $migrationInfo['class'];
        
        // Load the migration file
        require_once($filepath);
        
        // Check if class exists
        if (!class_exists($className)) {
            return array(false, "Migration class $className not found in $filepath");
        }
        
        // Instantiate and run migration
        try {
            $migration = new $className();
            $description = $migration->getDescription();
            
            // Calculate checksum before running
            $checksum = $this->calculateChecksum($filepath);
            
            // Run the migration
            $migration->up();
            
            // Record as applied
            $this->recordMigration($version, $description, $checksum);
            
            return array(true, "Migration $version applied: $description");
        } catch (Exception $e) {
            return array(false, "Migration $version failed: " . $e->getMessage());
        }
    }
    
    /**
     * Run all pending migrations
     * @return array [notices, error]
     */
    public function runPendingMigrations() {
        $notices = array();
        
        // Check for sequence gaps
        $gapWarnings = $this->checkSequenceGaps();
        if (!empty($gapWarnings)) {
            foreach ($gapWarnings as $warning) {
                $notices[] = "WARNING: $warning";
            }
        }
        
        // Check for checksum mismatches
        $checksumWarnings = $this->validateChecksums();
        if (!empty($checksumWarnings)) {
            foreach ($checksumWarnings as $version => $warning) {
                $notices[] = "WARNING: $warning";
            }
        }
        
        // Get pending migrations
        $pending = $this->getPendingMigrations();
        
        if (empty($pending)) {
            $notices[] = "No pending migrations.";
            return array($notices, null);
        }
        
        $notices[] = "Found " . count($pending) . " pending migration(s).";
        
        // Run each pending migration
        foreach ($pending as $version => $info) {
            list($success, $message) = $this->runMigration($info);
            
            if (!$success) {
                $error = new ObibError($message);
                return array($notices, $error);
            }
            
            $notices[] = $message;
        }
        
        return array($notices, null);
    }
    
    /**
     * Bootstrap existing installation
     * Marks migrations 001-006 as applied for existing 0.8.1 installations
     * @return array [notices, error]
     */
    public function bootstrap() {
        $notices = array();
        
        // Check current database version
        $currentVersion = $this->getCurrentDatabaseVersion($this->tablePrfx);
        
        if ($currentVersion === false) {
            $error = new ObibError("No existing OpenBiblio database found. Please perform a fresh install.");
            return array(null, $error);
        }
        
        if ($currentVersion !== OBIB_LATEST_DB_VERSION) {
            $error = new ObibError(
                "Database version is $currentVersion. " .
                "Please upgrade to " . OBIB_LATEST_DB_VERSION . " first using the old upgrade system."
            );
            return array(null, $error);
        }
        
        // Create schema_migrations table
        $this->createSchemaMigrationsTable();
        $notices[] = "Created schema_migrations table.";
        
        // Mark migrations 001-006 as applied (representing 0.8.1)
        $bootstrapMigrations = array(
            '001' => 'Upgrade 0.3.0 to 0.4.0',
            '002' => 'Upgrade 0.4.0 to 0.5.2',
            '003' => 'Upgrade 0.5.2 to 0.6.0',
            '004' => 'Upgrade 0.6.0 to 0.7.0',
            '005' => 'Upgrade 0.7.0 to 0.7.1',
            '006' => 'Upgrade 0.7.1 to 0.8.1'
        );
        
        foreach ($bootstrapMigrations as $version => $description) {
            // Use a dummy checksum for bootstrap migrations
            $checksum = md5("bootstrap_$version");
            $this->recordMigration($version, $description, $checksum);
            $notices[] = "Marked migration $version as applied: $description";
        }
        
        $notices[] = "Bootstrap complete. Database is now at migration 006 (version 0.8.1).";
        
        return array($notices, null);
    }
    
    /**
     * Perform upgrade: bootstrap if needed, then run pending migrations
     * @return array [notices, error]
     */
    public function performUpgrade() {
        $notices = array();
        
        // Check if schema_migrations table exists
        if (!$this->schemaMigrationsTableExists()) {
            // Need to bootstrap
            $notices[] = "Migrating to new migration system...";
            list($bootstrapNotices, $error) = $this->bootstrap();
            
            if ($error) {
                return array(null, $error);
            }
            
            $notices = array_merge($notices, $bootstrapNotices);
        }
        
        // Run pending migrations
        list($migrationNotices, $error) = $this->runPendingMigrations();
        
        if ($error) {
            return array($notices, $error);
        }
        
        $notices = array_merge($notices, $migrationNotices);
        
        return array($notices, null);
    }
}
