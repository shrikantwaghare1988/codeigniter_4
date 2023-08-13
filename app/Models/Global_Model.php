<?php

namespace App\Models;

use CodeIgniter\Model;

class Global_Model extends Model
{
	public $db ;

	function __construct() 
	{
		//$this->db  = \Config\Database::connect();
		$this->db = db_connect();       
    }
    public function get_all($table, $select = "*",$sort = "", $result_type = 'object')
    {
    	//----get all data from table----

    	$builder = $this->db->table($table);
    	$builder->select($select);
    	if($sort!="")
    	{
    		$builder->orderBy($sort);
    	}			
		$result   = $builder->get()->getResult($result_type);

		$sql = $this->db->getLastQuery()->getQuery();
		//pre($sql);

		return $result;       
    }
    public function get_row($table,$id,$result_type = 'object') 
    {  
    	//----get only single row----

        $builder = $this->db->table($table);
        $builder->limit(1); 
        $builder->where(['id' => $id]);
        if($result_type == 'array')
        {
        	$result = $builder->get()->getRowArray();
        }
        else
        {
        	$result = $builder->get()->getRow();
        }    	
    	return $result;
    }
    public function get_row_col($table,$col,$id,$result_type = 'object')
    {
    	//--get only single row with col condition---

        $builder = $this->db->table($table);        
        $builder->where([$col => $id]);
        $result   = $builder->get()->getResult($result_type);        	
    	return $result;
    }
    public function get_where($table,$where,$result_type = 'object')
    {
    	$builder = $this->db->table($table);        
        $builder->where($where);
        $result   = $builder->get()->getResult($result_type); 
        //pre($this->db->getLastQuery()->getQuery());		     	
    	return $result;
    }
    public function get_custom_query($query,$result_type = 'object')
    {
    	//----get the custom query data---
    	
    	$query = $this->db->query($query);
    	$result = $query->getResult();
    	return $result;
    }
    function getTablelist($args)
    {
        $fields = isset($args['fields']) ? $args['fields'] : "";
        $sTable = $args['sTable'];
        $joinlist = isset($args['joinlist']) ? $args['joinlist'] : "";
        $group_by = isset($args['group_by']) ? $args['group_by'] : "";
        $where = isset($args['where']) ? $args['where'] : "";
        $sorting = isset($args['sorting']) ? $args['sorting'] : "";
        $limit = isset($args['limit']) ? $args['limit'] : 0;
        $offset = isset($args['offset']) ? $args['offset'] : 0;
        $countOrResult = isset($args['countOrResult']) ? (strlen(trim($args['countOrResult'])) === 0 ? "result" : $args['countOrResult']) : "result";

        $showQuery = isset($args['showQuery']) ? $args['showQuery'] : false;
        $showError = isset($args['showError']) ? $args['showError'] : false;

        $filter_where = isset($args['filter_where']) ? $args['filter_where'] : false;
        $filter_prefix = isset($args['filter_prefix']) ? $args['filter_prefix'] : '';
        $do_filter = isset($args['do_filter']) ? $args['do_filter'] : false;
        $filter_condition = isset($args['filter_condition']) ? $args['filter_condition'] : 'or'; 

        $builder = $this->db->table($sTable);      

        if (strlen(trim($fields)) > 0) {
            $builder->select($fields, false);
        }        

        if (isset($joinlist) && is_array($joinlist)) {
            foreach ($joinlist as $join) {
                $builder->join($join["table"], $join["condition"], !isset($join["type"]) ? "" : $join["type"]);
            }
        }

        if (strlen(trim($group_by)) > 0) {
            $builder->groupBy($group_by);
        }

        if (is_array($where)) {
            $builder->where($where);
        } else if (strlen(trim($where)) > 0) {
            $builder->where($where);
        }

        if ($countOrResult === "result" || $countOrResult === "array") {
            if (strlen(trim($sorting)) > 0) {
                $builder->orderBy($sorting);
            }
            if ($limit > 0) {
                $builder->limit($limit, $offset);
            }
        }
        
        if ($countOrResult === "count")
        {
          $count = $builder->countAllResults();  
        }        

        $builder = $builder->get();

        if ($showQuery) {            
            echo $this->db->getLastQuery()->getQuery();
        }
               
        if ($countOrResult === "row") {
            return $builder->getRow();            
        } 
        else if ($countOrResult === "rowarray") {
            return $builder->getRowArray();
        }
        else if ($countOrResult === "count") {            
            return $count;
        }
        elseif ($countOrResult === "result") {
            return $builder->getResult();
        }
        elseif ($countOrResult === "array") {
            return $builder->getResult('array');
        }
    }
}	