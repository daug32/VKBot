<?php

if(!isset($_REQUEST)) return;

$event = json_decode(file_get_contents("php://input"), true);
if(!isset($event) || !isset($event["type"])) 
{
    echo "incorrect input";
    return;
}
    
require("ChatBot.php");

$database = new mysqli("___", "___", "___", "___");
$bot = new ChatBot($database);
$bot->Exec($event);

echo "ok";


?>
