<?php

namespace App\Controllers;
use App\Models\User_Model;
use App\Models\Global_Model;

class Home extends BaseController
{
	public $db ;

	public function __construct()
    {
        
        //$this->db  = \Config\Database::connect();
        $this->db = db_connect();
        
        
    }
    public function index(): string
    {
        return view('welcome_message');
    }
    public function test()
    {
        echo "welcome to php";
    }
    public function test2()
    {
    	$model = new User_Model();
		$data = $model->getUsers();
		pre($data);
    }
    public function test3()
    {    	
		$builder = $this->db->table('user_details');	
		//$sql = $builder->getCompiledSelect();
		//echo $sql;	
		//$query   = $builder->get()->getResult();
		$query   = $builder->get()->getResult('array');

		pre($query);
    }
    public function test4()
    {
    	$id = 4;

    	$builder = $this->db->table('user_details')->select('first_name,last_name');
    	//$builder->select('first_name,last_name');
    	//$query = $builder->getWhere(['id' => $id], $limit, $offset);
    	$result = $builder->getWhere(['user_id' => $id])->getResult('array');
    	pre($result);
    }
    public function test5()
    {
    	$id = 4;
    	$builder = $this->db->table('user_details');	
		$builder->where("user_id = 3");
		$data   = $builder->get()->getResult('array');
		pre($data);
    }
    public function test6()
    {
    	$gm = new Global_Model();
    	$data = $gm->get_all('emp','first_name,last_name','id desc','array');
    	if($data)
    	{
    		pre($data);
    	}
    	else
    	{
    		echo "no data";
    	}
    	
    }
    public function test7()
    {
    	$gm = new Global_Model();
    	$data = $gm->get_row('emp',9);
    	pre($data);    	
    }
    public function test8()
    {
    	$gm = new Global_Model();
    	$data = $gm->get_row_col('emp','first_name','Mara');
    	pre($data);    	
    }
    public function test9()
    {
    	$gm = new Global_Model();
    	$data = $gm->get_where('emp',"gender='Male'",'array');    	
    	pre($data);    	
    }
    public function test10()
    {
    	$gm = new Global_Model();
    	$data = $gm->get_custom_query("select first_name from emp order by id desc",'array');    	
    	pre($data);    	
    }
}
