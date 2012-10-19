<?php

include __DIR__."/include.php";

use Voodoo\Core;

$DB = new Core\VoodOrm(getPdo());

$users = $DB->user(); // or $DB->table("user");

$data = array(
    "name" => "Mardix",
    "created_datetime" => $users->DateTime()
);

try{
    $user = $voodOrm->insert($data);
    echo "Hello {$user->name}! Inserted on: {$user->created_datetime}";
    
} catch (Exception $e) {
    echo "ERROR: ". $e->getMessage();
}
