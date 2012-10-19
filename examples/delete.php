<?php

include __DIR__."/include.php";

use Voodoo\Core;

$DB = new Core\VoodOrm(getPdo());

$users = $DB->user(); // or $DB->table("user");

$userId = 1;

try{
    
    $user = $users->findOne($userId);
    
    if ($user) {
        $user->delete();
    }

} catch (Exception $e) {
    echo "ERROR: ". $e->getMessage();
}