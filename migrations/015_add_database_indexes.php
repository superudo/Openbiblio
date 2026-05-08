<?php
/* This file is part of a copyrighted work; it is distributed with NO WARRANTY.
 * See the file COPYRIGHT.html for more details.
 */

require_once(dirname(__FILE__) . "/../classes/Migration.php");

/**
 * Migration 015: Add database indexes for performance
 * 
 * Adds missing indexes on frequently-queried columns:
 * - biblio_field (tag, subfield_cd) - Every search that touches MARC fields
 * - biblio_copy (status_cd) - Availability filtering on search results
 * - member (barcode_nmbr) - Member lookup at checkout
 * - biblio (collection_cd, material_cd) - Collection/material filtering
 * - member (last_name) - Member search and sorting
 * - biblio_status_hist (status_begin_dt) - History ordering
 */
class Migration015 extends Migration {
    
    public function getDescription() {
        return "Add database indexes for performance";
    }
    
    public function up() {
        $prfx = $this->getTablePrefix();
        
        // High priority indexes
        $this->exec("ALTER TABLE {$prfx}biblio_field 
                     ADD INDEX idx_tag_subfield (tag, subfield_cd)");
        
        $this->exec("ALTER TABLE {$prfx}biblio_copy 
                     ADD INDEX idx_status_cd (status_cd)");
        
        $this->exec("ALTER TABLE {$prfx}member 
                     ADD INDEX idx_barcode_nmbr (barcode_nmbr)");
        
        // Medium priority indexes
        $this->exec("ALTER TABLE {$prfx}biblio 
                     ADD INDEX idx_collection_cd (collection_cd)");
        
        $this->exec("ALTER TABLE {$prfx}biblio 
                     ADD INDEX idx_material_cd (material_cd)");
        
        $this->exec("ALTER TABLE {$prfx}member 
                     ADD INDEX idx_last_name (last_name)");
        
        $this->exec("ALTER TABLE {$prfx}biblio_status_hist 
                     ADD INDEX idx_status_begin_dt (status_begin_dt)");
    }
}
