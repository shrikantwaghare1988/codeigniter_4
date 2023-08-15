<?php

namespace App\Controllers;
use App\Models\User_Model;
use App\Models\Global_Model;

class Ajax extends BaseController
{
    public $db ;

    public function __construct()
    {
        //$this->db  = \Config\Database::connect();
        $this->db = db_connect();
    }
    public function index(): string
    {
        return view('Ajax/form');
    }   
    public function save_ajax()
    {
        echo "ok";die;
    }
    public function form1()
    {
        return view('Ajax/form1');
    }
    public function save_form1()
    {
        $error = array();
        //pre($_POST);die;
        $data['full_name'] = $_POST['full_name'];
        $flag = true;
        $error['full_name'] = "";
        $error['email'] = "";
        $error['mobile_no'] = "";
        
        if($_POST['full_name'] == "")
        {
            $error['full_name'] = "Full Name canot be empty.";
            $flag = false;
        }
        if($_POST['mobile_no'] == "")
        {
            $flag = false;
            $error['mobile_no'] = "Mobile No canot be empty.";
        }
        if(!preg_match('/^[0-9]{10}+$/', $_POST['mobile_no']))
        {
            $flag = false;
            $error['mobile_no'] = "Plz enter valid mobile no.";
        }
        if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL))
        {
            $flag = false;
            $error['email'] = "Plz enter valid email id.";
        }


        if($flag)
        {
            echo "ok";
        }
        else
        {
            echo json_encode($error);
        }        
    }
  
}
