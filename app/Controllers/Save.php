<?php

namespace App\Controllers;
use App\Models\User_Model;
use App\Models\Global_Model;

class Save extends BaseController
{
	public $db ;

	public function __construct()
    {        
        //$this->db  = \Config\Database::connect();
        $this->db = db_connect();
    }
    
    public function test()
    {
        //----save the data----

        $gm = new Global_Model();
        $args = array(
            'mode' => "Add",
            'table' => "emp",
            'needID' => "1",
            'tableData' => array
            (
                'first_name' => 'Shrikant',
                'last_name' => 'Waghare',
                'gender' => 'Male',
                'country' => 'India',
                'age' => '36',
                'doj' => '2023-08-10',               
                'created_date' => date('Y-m-d')               
            )
        );
        $data = $gm->data_change($args);
        pre($data);
    } 
    public function test2()
    {
        //----save the data----

        $gm = new Global_Model();
        $args = array(
            'mode' => "Edit",
            'table' => "emp",
            'needID' => "1",
            'showQuery' => true,
            'tableData' => array
            (
                'first_name' => 'Shrikant 107',
                'last_name' => 'Waghare',
                'gender' => 'Male',
                'country' => 'India',
                'age' => '36',
                'doj' => '2023-08-10',               
                'created_date' => date('Y-m-d')               
            ),
            'where' => array('id' => 107)
        );
        $data = $gm->data_change($args);
        pre($data);
    }
    public function test3()
    {
        //----save the data----

        $gm = new Global_Model();
        $args = array(
            'mode' => "Edit",
            'table' => "emp",
            'id' => "109",
            'needID' => "1",
            'showQuery' => true,
            'tableData' => array
            (
                'first_name' => 'Shrikant 107',
                'last_name' => 'Waghare',
                'gender' => 'Male',
                'country' => 'India',
                'age' => '36',
                'doj' => '2023-08-10',               
                'created_date' => date('Y-m-d')               
            )
        );
        $data = $gm->data_change($args);
        pre($data);
    }
    public function test4()
    {
        //----save the data----

        $gm = new Global_Model();
        $args = array(
            'mode' => "Edit",
            'table' => "emp",
            'where' => "id=108",
            'needID' => "1",
            'showQuery' => true,
            'tableData' => array
            (
                'first_name' => 'Shrikant 107',
                'last_name' => 'Waghare',
                'gender' => 'Male',
                'country' => 'India',
                'age' => '36',
                'doj' => '2023-08-10',               
                'created_date' => date('Y-m-d')               
            )
        );
        $data = $gm->data_change($args);
        pre($data);
    }
    public function test5()
    {
        //----save the data----

        $gm = new Global_Model();
        $args = array(
            'mode' => "Del",
            'table' => "emp",
            //'where' => "id=108",
            'needID' => "1",
            'showQuery' => true,
            'tableData' => array('id' => '108')
        );
        $data = $gm->data_change($args);
        pre($data);
    }
    public function test6()
    {
        //----save the data----

        $gm = new Global_Model();
        $args = array(
            'mode' => "Del",
            'table' => "emp",
            //'where' => "id=108",
            'needID' => "1",
            'showQuery' => true,
            'tableData' => array('id >' => '104')
        );
        $data = $gm->data_change($args);
        pre($data);
    }
    public function test7()
    {
        //----save the data----

        $gm = new Global_Model();
        $args = array(
            'mode' => "Del",
            'table' => "emp",
            //'where' => "id=108",
            'needID' => "1",
            'showQuery' => true,
            'tableData' => "id = 104"
        );
        $data = $gm->data_change($args);
        pre($data);
    }
    public function test8()
    {
        //----save the data----

        $gm = new Global_Model();        

        $row = array
            (
                'first_name' => 'Shrikant 107',
                'last_name' => 'Waghare',
                'gender' => 'Male',
                'country' => 'India',
                'age' => '36',
                'doj' => '2023-08-10',               
                'created_date' => date('Y-m-d')               
            );

        $tbl_data = [$row,$row];

        $args = array(
                'table' => "emp",
                'data' => $tbl_data,
                'showQuery' => true,              
            );

        $data = $gm->insert_batch($args);
        pre($data);
    }

}
