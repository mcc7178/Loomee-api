<?php
namespace App\Utils\Bsc;

class Callback{
  function __invoke($error,$result){
    //$this->error = $error;
		if($error) throw $error;
    $this->result = $result;
  }
}

