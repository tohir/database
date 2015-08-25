<?php

/**
 * Base Database Class
 *
 * All DB function should go into a class called DBSQL that extends this class
 *
 */

namespace Tohir;


// Unfortunately, the author DID NOT put an autoload in his composer.json!!

require_once 'PDOWrapper.php';

abstract class Database
{
    protected $db;
    
    public function __construct($dbServer, $dbName, $dbUser, $dbPassword, $dbPort=NULL, $dbType='mysql', $databaseSlaves=array())
    {
        $this->db = PDOWrapper::instance();
        
        // Setup Master Connection
        $this->db->configMaster($dbServer, $dbName, $dbUser, $dbPassword, $dbPort, $dbType);
        
        // UTF8 Setup - Run on DB Master
        $this->db->query("SET NAMES 'UTF8';", array(), TRUE);
        
        
        // Get Slaves
        $databaseSlaves = AppConfig::get('database_slaves');
        
        if (!empty($databaseSlaves)) {
            foreach ($databaseSlaves as $slave)
            {
                $this->db->configSlave($slave['db_server'], $slave['db_name'], $slave['db_username'], $slave['db_password'], $slave['db_port'], $slave['db_type']);
            }
            
            // UTF8 Setup - Run on DB Slave as well
            $this->db->query("SET NAMES 'UTF8';", array(), FALSE);
        }
        
    }
    
    public function loadModel($modelName)
    {
        try {
            $className = 'DBModel_'.$modelName;
            
            $object = new $className($this->db);
            return $object;
            
        } catch (Exception $e) {
            die('Unable to load model - '.$modelName);
        }
    }
    
    /**
     * Method to parse a data array, and recreate one with just the keys needed for a table
     * @param array $data Data passed to be added
     * @param array $columns Keys Needed for the Table
     * @return array Columns and Data
     */
    protected function prepareTableData($data, $columns)
    {
        $returnArray = array();
        
        foreach ($columns as $key)
        {
            if (isset($data[$key])) {
                $returnArray[$key] = (trim($data[$key]) === '') ? NULL : trim($data[$key]);
            }
        }
        
        return $returnArray;
    }
    
    public function getRow($table, $column, $value)
    {
        return $this->db->selectFirst($table, array($column => $value));
    }
    
    public function updateRow($table, $column, $value, $data)
    {
        return $this->db->update($table, $data, array($column => $value));
    }
    
    public function getErrorMessage()
    {
        return $this->db->getErrorMessage();
    }
    
}