<?php

if(!isset($_REQUEST)) return;

$event = json_decode(file_get_contents("php://input"), true);
if(!$event || !isset($event["type"])) 
{
    echo "incorrect input";
    return;
}
$message = $event["object"]["message"];

if($event["type"] == "message_new" && $message["text"][0] == "/") 
{
    require_once("ChatBot.interface.php");
    
    $command = explode(" ", $message["text"]);
    $senderId = $message["from_id"];
    $peerId = $message["peer_id"];

    $database = new mysqli("localhost", "a0620713_admin", "4b6l3kNY", "a0620713_chats");
    $bot = new IChatBot($database);
    $bot->Exec($senderId, $peerId, $command);
}
echo "ok";


?>
