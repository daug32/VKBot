<?php

require_once("vk_api.php");

class ChatBot 
{
    public mysqli $db;
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
    
    function __construct($databaseUserName, $databasePassword, $databaseName)
    {
        $this->db = new mysqli(
            "localhost", 
            $databaseUserName, 
            $databasePassword, 
            $databaseName
        );
        if($this->db->connect_error)
        {
            error_log($this->db->connect_error);
            throw new Exception("Failed to access the database");
        }
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
            $this->$command($peerId);
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
            $userId = $options[1];
            if(!isset($userId)) 
            {
                VkApi::SendMessage($peerId, "Не указан ID пользователя.");
                return;
            }
            $userId = $this->FormatId($userId);

            //Get user's info
            $userObject = VkApi::GetUserInfoByScreenName($userId);
            if(isset($userObject["error"]))    
            {
                VkApi::SendMessage($peerId, "Неверный ID пользователя.");
                return;
            }           
            $userObject = $userObject["response"][0];
            
            $userId = $userObject["id"];
            $userName = $userObject["first_name"]." ".$userObject["last_name"];
            $userName = "[id".$userId."|".$userName."]";
            
            //Execute targeted command
            $command = $this->supportedCommands["admin"][$command];
            $this->$command($userId, $userName, $peerId, $options[2]);
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
        return str_replace(["[", "@", "]"], "", explode("|", $id)[0]);
    }
    //Check whether this command exist in targeted command system or not
    public function IsCommand($command, $commandSystem)
    {
        return in_array($command, array_keys($this->supportedCommands[$commandSystem]));
    }
    //Create new table for that chat if it doesn't exist
    public function CheckForTable($peer_id)
    {
        $peer_id = "chat_".$peer_id;
        $result = $this->db->query("SHOW TABLES;");
        if(!$result) {
            error_log("cannot access the database");
            throw new Exception("cannot access the database");
        }
        $result = $result->fetch_row();

        //return if table exist
        if(isset($result) && in_array($peer_id, $result)) return true;
        
        $query = "CREATE TABLE $peer_id (UserID int(1), Warns int(1), Mute date, Ban date);";
        $result = $this->db->query($query);
        return true;
    }


    /*
    * Block of GENERAL commands
    */
    public function Help($chatId)
    {
        $message = 
            "Список команд.\n".
            "============ Администрирование ============\n".
            "1) /предупреждение <id пользователя>\n".
            "2) /мут <id пользователя> <время в минутах>\n".
            "3) /кик <id пользователя>\n";
        VkApi::SendMessage($chatId, $message);
    }
    public function Settings()
    {
    }
    

    /*
    * Block of ADMIN commands
    */
    public function GiveWarn($userId, $userName, $chatId)
    {
        $this->CheckForTable($chatId);
        
        $query = "SELECT Warns FROM chat_$chatId WHERE UserID=$userId;";
        $result = $this->db->query($query);
        $result = $result->fetch_assoc();
        
        $warns = 1;
        $needMute = false;
        
        //if user isn't in table, add him
        if(!isset($result))
        {
            $query = "
                INSERT INTO chat_$chatId (UserID, Warns) 
                VALUES ($userId, 1);";
        }
        //if user is in table, update his info
        else
        {
            $warns = $result["Warns"] + 1;
            if($warns > 2)
            {
                $warns = 0;
                $needMute = true;
            }
            $query = "
                UPDATE chat_$chatId 
                SET Warns = $warns 
                WHERE UserID = $userId;";
        }
        $this->db->query($query);
        
        if($needMute)
        {
            $this->GiveMute($userId, $userName, $chatId, 30);
            return; 
        }
        
        $message = "Выдано предупреждение пользователю $userName. Предупреждений: $warns/3.";
        VkApi::SendMessage($chatId, $message);    
    }
    public function GiveMute($userId, $userName, $chatId, $time)
    {
        $message = "Пользователь ".$userName." теперь в муте на ".$time." минут.";
        VkApi::SendMessage($chatId, $message);           
    }
    public function Kick($userId, $userName, $chatId)
    {
        VkApi::KickUser($userId, $chatId);
        $message = $userName." был исключен :(";
        VkApi::SendMessage($chatId, $message);  
    }

    
    /*
    * Block of MAILING commands
    */
    public function SendLessonPlan()
    {
    }
    public function SendCommunitiesUpdates()
    {
    }
}
?>

