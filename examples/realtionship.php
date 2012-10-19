<?php
/**
 * Relationship
 */
include __DIR__."/include.php";

use Voodoo\Core;

$DB = new Core\VoodOrm(getPdo());

$users = $DB->user(); // or $DB->table("user");

$userId = 1;

try{
    
    $allUsers = $users->find();
	
    foreach ($allUsers as $user) {
        /**
        * Connect to the 'friend' table = $user->friend();
        * In the back, it does a ONE To MANY relationship 
        * SELECT * FROM friend WHERE friend.user_id = user.id 
        */
        $allFriends = $user->friend();
        foreach ($allFriends as $friend) {
            echo "{$friend->friend_id} : ";

            /**
            * We got the friend's entry, we want to go back in the user table
            * So we link back the friend table to the user table
            * SELECT * FROM user WHERE user.id = friend.friend_id LIMIT 1 
            * It will do a ONE to One relationship
            */
            echo $friend->user(Core\VoodOrm::REL_HASONE, "friend_id")->name;

            echo "\n";
        }
    }
    
    // Same as above but with just one user
    $user = $users->findOne($userId);

    if($user) {

        foreach ($user->friend() as $friend) {

            echo "{$friend->friend_id} : ";

            echo $friend->user(Core\VoodOrm::REL_HASONE, "friend_id")->name;

            echo "\n";
        }
    }
    
    
  print_r($users->getQueryProfiler()) ; 
    
} catch (Exception $e) {
    echo "ERROR: ". $e->getMessage();
}