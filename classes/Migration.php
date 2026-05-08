<?php
/* This file is part of a copyrighted work; it is distributed with NO WARRANTY.
 * See the file COPYRIGHT.html for more details.
 */

require_once(dirname(__FILE__) . "/InstallQuery.php");

/**
 * Base class for database migrations.
 * 
 * Each migration should extend this class and implement:
 * - getDescription(): Return a short description of the migration
 * - up(): Apply the migration changes
 * 
 * Migrations have access to all InstallQuery methods for database operations.
 */
abstract class Migration extends InstallQuery {
    
    /**
     * Get the table prefix for this installation
     * @return string Table prefix (e.g., '' or 'obiblio_')
     */
    protected function getTablePrefix() {
        return DB_TABLENAME_PREFIX;
    }
    
    /**
     * Get a short description of this migration
     * @return string Description
     */
    abstract public function getDescription();
    
    /**
     * Apply the migration
     * This method should contain all SQL statements and logic to upgrade the schema
     */
    abstract public function up();
    
    /**
     * Execute a SQL statement
     * Wrapper around parent exec() for clarity
     * @param string $sql SQL statement to execute
     * @return mixed Result of the query
     */
    public function exec($sql) {
        return parent::exec($sql);
    }
    
    /**
     * Execute a SQL file
     * @param string $filepath Path to SQL file
     * @param string $tablePrfx Table prefix to use
     */
    public function executeSqlFile($filepath, $tablePrfx = DB_TABLENAME_PREFIX) {
        return parent::executeSqlFile($filepath, $tablePrfx);
    }
}
