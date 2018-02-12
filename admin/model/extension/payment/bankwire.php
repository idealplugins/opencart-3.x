<?php

/**
 *
 *    iDEALplugins.nl
 *  TargetPay plugin for Opencart 2.0+
 *
 *  (C) Copyright Yellow Melon 2014
 *
 * @file        TargetPay Admin Model
 * @author        Yellow Melon B.V. / www.idealplugins.nl
 *
 */
require_once ("../system/library/targetpay.class.php");

class ModelExtensionPaymentBankwire extends Model
{

    private $methodName = "bankwire";

    public function createTable()
    {
        $this->db->query(TargetPayCore::getCreateTableQuery($this));
        // check if new version of this plugin is installed and used or not
        $data = $this->db->query(TargetPayCore::getNewVersionDataQuery($this));
        
        if ($data->num_rows == 0) {
            $oldTable = DB_PREFIX . $this->methodName;
            $newTable = DB_PREFIX . TargetPayCore::TARGETPAY_PREFIX . $this->methodName;
        
            $this->migrateTable($oldTable, $newTable);
        }
    }
    
    public function getMethodName()
    {
        return $this->methodName;
    }

    /**
     * Need to check for existing extension first then only migrate if the old
     * table exist or match the naming style from the previous plugin version.
     *
     * @param string $oldTable
     * @param string $newTable
     */
    
    private function migrateTable($oldTable, $newTable)
    {
        $sqlCheckForMethodInstalled = 'select extension_id from ' . DB_PREFIX . 'extension where type="payment" and code="' . $this->methodName . '" limit 1';
        
        $findOldTableSql = TargetPayCore::getOldTableCheckQuery($this);
        
        $tableFound = $this->db->query($findOldTableSql);
        $installed = $this->db->query($sqlCheckForMethodInstalled);
        
        if ($installed->num_rows > 0 && $tableFound->num_rows > 0) {
            $countOld = $this->db->query("SELECT count(order_id) as count FROM $oldTable");
            
            $sqlInsert = "INSERT INTO " . $newTable .
                " (`order_id`, `method`, `{$this->methodName}_txid`, `{$this->methodName}_response`, `paid`) " .
                "SELECT `order_id`, `method`, `{$this->methodName}_txid`, `{$this->methodName}_response`, `paid` FROM " .
                $oldTable . ";";
            
            $this->db->query($sqlInsert);
            
            $countNew = $this->db->query("SELECT count(order_id) as count FROM $newTable");
            
            if ($countNew == $countOld) {
                $this->db->query("DROP TABLE $oldTable");
            }
        }
    }
}
