<?php

namespace Tohir;

/**
 * Database Model Tree Class using Modfied Pre-Order Traversal
 *
 * @todo Memcache
 */
abstract class DBTreeModel extends \Tohir\DBModel
{
    
    // Columns for the Modified Pre-Order Traversal
    protected $parentTreeColumn = 'parent';
    protected $lftTreeColumn = 'lft';
    protected $rghtTreeColumn = 'rght';
    protected $levelTreeColumn = 'level';
    protected $itemOrderColumn = 'name';
    
    
    /* List of Hooks - These should be overriden */
    
    /**
     * Hook to run before adding a new record
     * @param array $data List of Fields and Values
     */
    protected function hook_before_add($data)
    {
        // Add these fields as is - they will be updated afterwards
        $data[$this->lftTreeColumn] = 0;
        $data[$this->rghtTreeColumn] = 0;
        $data[$this->levelTreeColumn] = 0;
        
        return $data;
    }
    
    /**
     * Hook to run after adding a new record
     * @param array $data List of Fields and Values
     * @param int|false $result If record was added, last increment id, else FALSE
     */
    protected function hook_after_add($result, $data)
    {
        $this->rebuild_tree(0, 0, 0);
        
        return $result;
    }
    
    /**
     * Hook to run after update a new record
     * @param array $data List of Fields and Values
     * @param int|false $result If record was added, last increment id, else FALSE
     */
    protected function hook_after_update($result, $data)
    {
        $this->rebuild_tree(0, 0, 0);
        
        return $result;
    }
    
    /**
     * Method to rebuild the tree left/right/level values using the modified preorder traversal approach
     *
     * This assumes the table has the following columns to support the tree structure:
     * id, parent_id, lft, rght, level
     * 
     * @example $this->rebuild_tree(0, 0, 0);
     * @param mixed $parent Parent Id to Start With
     * @param int $left Starting Left Value
     * @param int $level Starting Level Value
     */
    private function rebuild_tree($parent, $left, $level)
    {
        // the right value of this node is the left value + 1
        $right = $left+1;
        
        $result = $this->db->select($this->tableName, array($this->parentTreeColumn=>$parent), NULL, NULL, array($this->itemOrderColumn=>'asc'));
        
        var_dump($result);
        
        //$result = $this->db->fetch_all_array('SELECT id, name FROM '.$this->tableName.' WHERE parent_id="'.$parent.'" ORDER BY name;');
        
        foreach ($result as $row)
        {
            
            // recursive execution of this function for each
            // child of this node
            // $right is the current right value, which is
            // incremented by the rebuild_tree function
            $right = $this->rebuild_tree($row[$this->primaryKey], $right, $level+1);
        }
        // we've got the left value, and now that we've processed
        // the children of this node we also know the right value
        //$this->db->query('UPDATE '.$this->tableName.' SET lft='.$left.', rght='.$right.', level='.$level.' WHERE id="'.$parent.'";');
        
        $this->db->update($this->tableName, array($this->lftTreeColumn=>$left, $this->rghtTreeColumn=>$right, $this->levelTreeColumn=>$level), array('id'=>$parent));
        
        // return the right value of this node + 1
        return $right+1;
    }
    
    public function getPath($id, $includeSelf=TRUE)
    {
        $node = $this->get($id);
        
        if ($node == FALSE) {
            return FALSE;
        }
        
        if ($includeSelf) {
            $opr = '=';
        } else {
            $opr = '';
        }
        
        $where = ' WHERE ('.$this->lftTreeColumn." <{$opr} ".$node[$this->lftTreeColumn].' AND '.$this->rghtTreeColumn." >{$opr} ".$node[$this->rghtTreeColumn].')';
        
        
        $query = 'SELECT * FROM '.$this->tableName.$where.' ORDER BY '.$this->levelTreeColumn.', '.$this->lftTreeColumn;
        
        return $this->db->query($query);
    }
    
    public function getTree($topParentId = 0)
    {
        $where = '';
        
        if ($topParentId != 0) {
            $topGroup = $this->getRow($this->primaryKey, $topParentId);
            
            if ($topGroup == FALSE) {
                die('Group does not exists - <a href="/orphans">Check for Orphans</a>');
            }
            
            $where = ' WHERE ('.$this->lftTreeColumn.' > '.$topGroup[$this->lftTreeColumn].' AND '.$this->rghtTreeColumn.' < '.$topGroup[$this->rghtTreeColumn].')';
        }
        
        $query = 'SELECT * FROM '.$this->tableName.$where.' ORDER BY '.$this->levelTreeColumn.', '.$this->lftTreeColumn;
        
        $result = $this->db->query($query);
        
        $return = array();
        $alias = array();
        
        foreach ($result as $row)
        {
            $record = array('id'=>$row[$this->primaryKey], 'name'=>$row[$this->itemOrderColumn], 'left'=>$row[$this->lftTreeColumn], 'right'=>$row[$this->rghtTreeColumn], 'children'=>array());
            
            if ($row[$this->parentTreeColumn] == $topParentId) {
                $return['t_'.$row[$this->primaryKey]] = $record;
                $alias['t_'.$row[$this->primaryKey]] =& $return['t_'.$row[$this->primaryKey]];
            } else {
                $alias['t_'.$row[$this->parentTreeColumn]]['children']['t_'.$row[$this->primaryKey]] = $record;
                $alias['t_'.$row[$this->primaryKey]] =& $alias['t_'.$row[$this->parentTreeColumn]]['children']['t_'.$row[$this->primaryKey]];
            }
        }
        
        return $return;
    }
    
    public function displayTree($topParentId=0, $url='')
    {
        $tree = $this->getTree($topParentId);
        
        if (empty($tree)) {
            return '';
        }
        
        $str = '<ul class="tree">'; // @todo - move this to the options
        
        foreach ($tree as $node)
        {
            $str .= $this->drillTree($node, $url);
        }
        
        $str .= '</ul>';
        
        return $str;
    }
    
    protected function drillTree($node, $url)
    {
        $str = '<li><span>';
        
        if (!empty($url)) {
            $str .= '<a href="'.str_replace('[-ID-]', $node['id'], $url).'">';
        }
        
        $str .= htmlspecialchars($node['name']);
        
        if (!empty($url)) {
            $str .= '</a>';
        }
        
        $str .= '</span>';
        
        if (count($node['children']) > 0) {
            $str .= '<ul>';
            
            foreach($node['children'] as $child) {
                $str .= $this->drillTree($child, $url);
            }
            
            $str .= '</ul>';
        }
        
        $str .= '</li>';
        
        return $str;
    }
    
    public function getFormSelectOptions($currentId = FALSE)
    {
        $items = $this->db->select($this->tableName, NULL, NULL, NULL, array($this->lftTreeColumn=>'asc'));
        
        $options = array();
        
        if ($currentId == FALSE) {
            $hasDisabled = FALSE;
        } else {
            
            $node = $this->get($currentId);
            
            if ($node == FALSE) {
                $hasDisabled = FALSE;
            } else {
                $hasDisabled = TRUE;
            }
            
            
        }
        
        foreach ($items as $item)
        {
            $prepend = str_repeat('- ', $item[$this->levelTreeColumn] - 1);
            $optionArr = array('label'=>$prepend.$item[$this->itemOrderColumn], 'value'=>$item[$this->primaryKey]);
            
            // Make the current node and all its children disabled
            if ($hasDisabled && $item[$this->lftTreeColumn] >= $node[$this->lftTreeColumn] && $item[$this->rghtTreeColumn] <= $node[$this->rghtTreeColumn]) {
                $optionArr['disabled'] = 'disabled';
            }
            
            $options[] = $optionArr;
        }
        
        return $options;
    }
}