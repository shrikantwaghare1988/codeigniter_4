<?php

namespace App\Models;

use CodeIgniter\Model;

class User_Model extends Model
{
	protected $table      = 'user_details';

	protected $primaryKey = 'id';

	protected $returnType = 'array';

	//protected $allowedFields = ['name', 'price', 'quantity', 'status', 'created', 'description'];

	public function getUsers($id = false) 
	{
      if($id === false) {
        return $this->findAll();
      } else {
          return $this->getWhere(['id' => $id]);
      }
  	}
}
 
 	