<?php

include __DIR__."/include.php";

use Voodoo\Core;

$DB = new Core\VoodOrm(getPdo());

$users = $DB->user(); // or $DB->table("user");

$userId = 1;

try{
    
    $user = $users->findOne($userId);
    
    if ($user) {
        
        $user->name = "Changed Name";

        // or
        
        $user->set("name","Another name");
        
        // or 
        
        $user->set(array(
            "name" => "Another name changed"
        ));
        
        $user->update(); //or $user->save();
    }

} catch (Exception $e) {
    echo "ERROR: ". $e->getMessage();
}