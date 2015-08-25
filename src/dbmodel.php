<?php

namespace Tohir;

/**
 * Simple Database Model Class
 *
 */
abstract class DBModel
{
    protected $db;
    
    /**
     * @var string Name of Table
     */
    protected $tableName = 'tablename';
    
    /**
     * @var string Primary of Table
     */
    protected $primaryKey = 'id';
    
    /**
     * @var string The column holding the Date Inserted Field - auto populated if set
     */
    protected $dateInsertColumn = '';
    
    /**
     * @var string The column holding the Date Updated Field - auto populated if set
     */
    protected $dateUpdateColumn = '';
    
    /**
     * @var array List of Table Columns - Exclude primary key
     */
    protected $tableColumns = array(
            'column1',
            'column2',
        );
    
    /**
     * Constructor
     * @param object $dbObject PDO Wrapper
     */
    public function __construct($dbObject)
    {
        $this->db = $dbObject;
    }
    
    protected function loadModel($modelName)
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
     * Method to get a single row
     * @param int $id Primary Key Value
     * @return array|FALSE Record Details
     */
    public function get($id)
    {
        return $this->getRow($this->primaryKey, $id);
    }
    
    /**
     * Method to get a single row by column, value
     * @param int $id Primary Key Value
     * @return array|FALSE Record Details
     */
    public function getRow($column, $value)
    {
        return $this->db->selectFirst($this->tableName, array($column => $value));
    }
    
    /**
     * Method to get all records
     * @return array
     */
    public function getAll()
    {
        return $this->db->select($this->tableName);
    }
    
    /**
     * Method to add a single row
     * @param int $id Primary Key Value
     * @param array $data Column Values
     * @return int|false Insert ID or FALSE if failed
     */
    public function add($data)
    {
        // Run Hook
        $data = $this->hook_before_add($data);
        
        // Prepare Table Data
        $data = $this->prepareTableData($data, $this->tableColumns);
        
        // Auto set Date Added Value
        if (!empty($this->dateInsertColumn)) {
            $data[$this->dateInsertColumn] = date('Y-m-d H:i:s');
        }
        
        // Auto set Date Updated Value
        if (!empty($this->dateUpdateColumn)) {
            $data[$this->dateUpdateColumn] = date('Y-m-d H:i:s');
        }
        
        // Run the insert
        $result = $this->db->insert($this->tableName, $data);
        
        // Run the Hook
        $hookResult = $this->hook_after_add($result, $data);
        
        return $result;
    }
    
    /**
     * Method to update a single row
     * @param int $id Primary Key Value
     * @param array $data Column Values
     * @return boolean
     */
    public function update($id, $data)
    {
        // Run Hook
        $data = $this->hook_before_update($data);
        
        $data = $this->prepareTableData($data, $this->tableColumns);
        
        if (!empty($this->dateUpdateColumn)) {
            $data[$this->dateUpdateColumn] = date('Y-m-d H:i:s');
        }
        
        // Run the update
        $result = $this->db->update($this->tableName, $data, array($this->primaryKey => $id));
        
        // Run the Hook
        $result = $this->hook_after_update($result, $data);
        
        return $result;
    }
    
    /* List of Hooks - These should be overriden */
    
    /**
     * Hook to run before adding a new record
     * @param array $data List of Fields and Values
     */
    protected function hook_before_add($data)
    {
        return $data;
    }
    
    /**
     * Hook to run after updating a new record
     * @param int|false $result If record was added, last increment id, else FALSE
     * @param array $data List of Fields and Values
     */
    protected function hook_after_add($result, $data)
    {
        return $result;
    }
    
    /**
     * Hook to run before updating a new record
     * @param array $data List of Fields and Values
     */
    protected function hook_before_update($data)
    {
        return $data;
    }
    
    /**
     * Hook to run after adding a new record
     * @param boolean $result Update result
     * @param array $data List of Fields and Values
     */
    protected function hook_after_update($result, $data)
    {
        return $result;
    }
    
    public function getRecordCount()
    {
        $query = 'SELECT count('.$this->primaryKey.') AS count FROM '.$this->tableName;
        
        $result = $this->db->queryFirst($query);
        
        if ($result == 0) {
            return 0;
        } else {
            return (int)$result['count'];
        }
    }
    
    
    
    /* THIS BELOW NEEDS TO GO INTO A TRAIT */
    
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
    
    public function getErrorMessage()
    {
        return $this->db->getErrorMessage();
    }
    
    
}