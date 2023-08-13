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
}	