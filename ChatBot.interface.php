<?php

require_once("ChatBot.settings.php");
require_once("ChatBot.class.php");
require_once("vk_api.php");

class IChatBot
{
    public array $supportedCommands = 
    [
        "general" => 
        [
            "/помощь" => "Help",
            "/настройки" => "Settings"
        ],
        "admin" => 
        [
            "/предупреждение" => "GiveWarn", 
            "/кик" => "Kick", 
            "/мут" => "GiveMute"
        ],
        "mailing" => 
        [
            "/расписание" => "", 
            "/подписаться" => "", 
            "/отписаться" => ""
        ]
    ];  
    public ChatBot $bot;  

    function __construct($database, $settings = new ChatBotSettings())
    {
        $this->bot = new ChatBot($settings, $database);
    }

    public function Exec($senderId, $peerId, $options) 
    {
        $command = $options[0];

        /*
        * Make preppings for GENERAL commands and execute
        */
        if($this->IsCommand($command, "general"))
        {
            $command = $this->supportedCommands["general"][$command];
            $this->bot->$command($peerId);
            return;
        }

        /*
        * Make preppings for ADMIN commands and execute
        */
        if($this->IsCommand($command, "admin"))
        {
            //Return if message was send in personal chat
            if($senderId == $peerId) 
            {
                VkApi::SendMessage($peerId, "Эта команда не может работать в личке.");
                return;
            }
            
            //Format user's ID
            $userId = $this->FormatId($options[1]);
            if(!isset($userId)) 
            {
                VkApi::SendMessage($peerId, "Не указан ID пользователя.");
                return;
            }

            //Get user's info
            $userObject = $this->ChatMember($peerId, $userId);
            if(!$userObject)    
            {
                VkApi::SendMessage($peerId, "В чате не найден пользователь с таким ID.");
                return;
            }
            
            $userId = $userObject["id"];
            $userName = $userObject["first_name"]." ".$userObject["last_name"];
            $userName = "[id".$userId."|".$userName."]";
            
            //Execute targeted command
            $command = $this->supportedCommands["admin"][$command];
            $this->bot->$command($userId, $userName, $peerId, $options[2]);
            return;
        }

        /*
        * Make preppings for MAILING commands and execute
        */
        if($this->IsCommand($command, "mailing"))
        {
            
        }

        /*
        * Nothing happend - this's not a command
        */  
        VkApi::SendMessage($peerId, "Боюсь, что такой команды нет.");
    }    

    //Extract numeric id from [id0000000|@screen_name]
    public function FormatId($id)
    {
        $id = explode("|", $id)[0];
        return explode("id", $id)[1];
    }
    //Check whether this command exist in targeted command system or not
    public function IsCommand($command, $commandSystem)
    {
        return in_array($command, array_keys($this->supportedCommands[$commandSystem]));
    }
    public function ChatMember($peerId, $userId)
    {
        $response = VkApi::GetChatMembers($peerId);
        if($response["error"])
        {
            if($response["error"]["error_code"] == 917) 
                $this->bot->DeleteTable($peerId);
            return false;
        } 
        $response = $response["response"]["profiles"];
        foreach($response as $profile)
        {
            if($profile["id"] == $userId)
                return $profile;
        }
        return false;
    }
}

?>