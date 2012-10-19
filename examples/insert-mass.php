<?php

include __DIR__."/include.php";

use Voodoo\Core;

$DB = new Core\VoodOrm(getPdo());

$users = $DB->user(); // or $DB->table("user");

$data = array(
    array(
        "name" => "Faby",
        "created_datetime" => $users->DateTime(),
    ),
    array(
        "name" => "Seba",
        "created_datetime" => $users->DateTime(),
    ),
    array(
        "name" => "Raerae",
        "created_datetime" => $users->DateTime(),
    ),
    array(
        "name" => "Junior",
        "created_datetime" => $users->DateTime(),
    ),    
);

try{
    $totalInserted = $voodOrm->insert($data);
    echo "Total Insert: {$totalInserted}";
    
} catch (Exception $e) {
    echo "ERROR: ". $e->getMessage();
}
