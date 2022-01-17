<?php

if(!isset($_REQUEST)) return;

$event = json_decode(file_get_contents("php://input"), true);
if(!$event || !isset($event["type"])) 
{
    echo "incorrect input";
    return;
}

if($event["type"] == "message_new") 
{
    require_once("syntax_analizer.php");

    $senderId = $event["object"]["message"]["from_id"];
    $peerId = $event["object"]["message"]["peer_id"];
    $message = $event["object"]["message"]["text"];

    $bot = new Bot();

    $command = $bot->GetWordsIfCommand($message);
    if(!isset($command)) return;
    $bot->ExecuteCommand($senderId, $peerId, $command);

    echo "ok";
}
else "unsupported event";


?>
