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
    public function test11()
    {
        $gm = new Global_Model();
        $args = array(
                'countOrResult' => "result",
                'fields' => 'id,first_name,gender',
                //'limit' => 5,
                //'offset' => 4,
                'sorting' => "id asc",
                //'where' => "first_name = 'Dulce'",
                'where' => ['id'=>1,'first_name'=>'Dulce'],
                'showQuery' => true,
                //'group_by' => 'Gender',
                'sTable' => 'emp'
            );
        $data = $gm->getTablelist($args);
        pre($data);
    }
    public function test12()
    {
        $gm = new Global_Model();
        $args = array(
                'countOrResult' => "result",
                'fields' => 'u.id,u.full_name,l.city',
                'limit' => 5,
                'offset' => 4,
                'sorting' => "u.id asc",
                //'where' => "first_name = 'Dulce'",
                'joinlist' => 
                array(
                        array(
                            "table" => "location l",
                            "condition" => "u.loc_id = l.id",
                            "type" => "left"
                            )
                ),
                'showQuery' => true,
                //'group_by' => 'Gender',
                'sTable' => 'user u'
            );
        $data = $gm->getTablelist($args);
        pre($data);
    }
    public function test13()
    {
        $gm = new Global_Model();
        $args = array(
                'countOrResult' => "row",
                'fields' => 'u.id,u.full_name,l.city',
                'limit' => 5,
                'offset' => 4,
                'sorting' => "u.id asc",
                'where' => "full_name = 'Shrikant Waghare'",
                'joinlist' => array(
                                    array(
                                        "table" => "location l",
                                        "condition" => "u.loc_id = l.id",
                                        "type" => "left"
                                        )
                                    ),
                'showQuery' => true,
                //'group_by' => 'Gender',
                'sTable' => 'user u'
            );
        $data = $gm->getTablelist($args);
        pre($data);
    }
}
