<?php

if(!isset($_REQUEST)) return;

$event = json_decode(file_get_contents("php://input"), true);
if(!$event || !isset($event["type"])) 
{
    echo "incorrect input";
    return;
}

if($event["type"] == "message_new" && $event["object"]["message"]["text"][0] == "/") 
{
    require_once("chatBot.class.php");
    
    $command = explode(" ", $event["object"]["message"]["text"]);
    $senderId = $event["object"]["message"]["from_id"];
    $peerId = $event["object"]["message"]["peer_id"];

    $bot = new ChatBot("a0620713_admin", "4b6l3kNY", "a0620713_chats");
    $bot->Exec($senderId, $peerId, $command);
}
echo "ok";

// $userName = "a0620713_admin";
// $password = "4b6l3kNY";
// $database = "a0620713_chats";

// $driver = new mysqli_driver();
// $driver->report_mode = MYSQLI_REPORT_OFF;
// $db = new mysqli("localhost", $userName, $password, $database);
// if($db->connect_error) 
// {
//     echo "error";
//     return;
// }
// $tableName = "chat_2000000002";
// $userId = 266285756;

// $query = "SELECT * FROM `$tableName` WHERE UserID=$userId;";
// $user = $db->query($query);

// $user = $user->fetch_assoc();
// var_dump($user);


//echo ($user[0]["warns"]);

// $userState = '[{"warn":1}]';
// $query = "INSERT INTO `".$tableName."` (UserID, State) VALUES (".$userId.", '[{\"warn\":1}]');";
// $user = $db->query($query);


?>
