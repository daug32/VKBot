<?php

if(!isset($_REQUEST)) return;

$event = json_decode(file_get_contents("php://input"), true);
if(!isset($event) || !isset($event["type"])) 
{
    echo "incorrect input";
    return;
}
    
require("ChatBot.class.php");

$database = new mysqli("localhost", "a0620713_admin", "4b6l3kNY", "a0620713_chats");
$bot = new ChatBot($database);
$bot->Exec($event);

echo "ok";


?>
