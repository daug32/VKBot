<?php

require("vk_api.php");

class ChatBot
{
    public mysqli $db;

    public int $maxWarns = 3;
    public int $defaultMuteTime = 30;
    public int $defaultBanTime = 30;
    public string $commandMark = "/";

    public int $groupId;
    public int $peerId;
    public string $tableName;

    public array $supportedCommands = 
    [
        "general" => 
        [
            "/помощь" => "Help",
            "/настройки" => "SetSettings",

            "/help" => "Help",
            "/settings" => "SetSettings"
        ],
        "admin" => 
        [
            "/предупреждение" => "GiveWarn", 
            "/кик" => "Kick", 
            "/мут" => "GiveMute",
            "/бан" => "Ban",

            "/warn" => "GiveWarn", 
            "/kick" => "Kick", 
            "/mute" => "GiveMute",
            "/ban" => "Ban"
        ]
    ];  

    function __construct($database)
    {
        $this->db = $database;

        $this->groupId = VK_API_GROUP_ID;
    }
    function __destruct()
    {
        $this->db->close();
    }

    
    public function Exec($event) 
    {
        if($event["type"] != "message_new") return;

        $event = $event["object"]["message"];

        $action = $event["action"];
        $senderId = $event["from_id"];
        $messageId = $event["conversation_message_id"];
        
        $this->peerId = $event["peer_id"];
        $this->tableName = "chat_".$this->peerId;

        $options = explode(" ", $event["text"]);

        //If message is not in a personal chat 
        if($this->peerId != $senderId)
        {
            $this->CheckForTable();
            $this->LoadSettings();

            //Return if banned user was invited
            if(isset($action))
            {
                $isAffected = $this->GetState($action["member_id"], "Ban") ?: 0;
                $isAffected = (bool)($isAffected > (int)strtotime("now"));
                if($action["type"] == "chat_invite_user" && $isAffected)
                {
                    VkApi::SendMessage($this->peerId, "Этот пользователь все еще находится в бане.");
                    VkApi::Kick($this->peerId - 2000000000, $action["member_id"]);
                    return;
                }
            }

            //Return if banned user was invited
            $isAffected = $this->GetState($senderId, "Mute") ?: 0;
            $isAffected = (bool)($isAffected > (int)strtotime("now"));
            if($isAffected)
            {
                VkApi::DeleteConversationMessage($this->peerId, $messageId);
                return;
            }
        }
        if($options[0][0] != $this->commandMark[0]) return;
        $command = $options[0];

        //Return if sender isn't admin
        if(!$this->IsAdmin($senderId))
        {
            VkApi::SendMessage($this->peerId, "Вам нужны права администратора для выполнения этой команды.");
            return;
        }

        /*
        * Make preppings for commands without special rights - GENERAL commands
        */
        if($this->IsCommand($command, "general"))
        {
            $command = $this->supportedCommands["general"][$command];
            $this->$command($options[1], $options[2]);
            return;
        }

        /*
        * Make preppings for commands WITH special rights - ADMIN commands
        */
        if($this->IsCommand($command, "admin"))
        {
            //Return if message was send in personal chat
            if($senderId == $this->peerId) 
            {
                VkApi::SendMessage($this->peerId, "Эта команда не может работать в личке.");
                return;
            }

            //Return if bot isn't chat administartor
            if(!$this->IsAdmin(-$this->groupId))
            {
                VkApi::SendMessage($this->peerId, "Я не админ - у меня нет прав выполнять эту команду.");
                return;
            }
            
            //Return if ID is incorrect
            // $userId = $this->FormatId($options[1]);
            $userId = explode(
                "id", 
                explode("|", $options[1])[0]
            )[1];
            if(!isset($userId)) 
            {
                VkApi::SendMessage($this->peerId, "Не указан ID пользователя.");
                return;
            }

            //Return if target user is the sender
            if($senderId == $userId)
            {
                VkApi::SendMessage($this->peerId, "Дурашка! Такие команды нельзя применять на себе.");
                return;
            }

            //Return if user is not a chat member
            $userObject = $this->ChatMemberInfo($userId);
            if(!isset($userObject["id"]))    
            {
                VkApi::SendMessage($this->peerId, "В чате не найден пользователь с таким ID.");
                return;
            }
            
            $userName = $userObject["first_name"]." ".$userObject["last_name"];
            $userName = "[id".$userId."|".$userName."]";
            
            //Execute targeted command
            $command = $this->supportedCommands["admin"][$command];
            $this->$command($userId, $userName, $options[2]);
            return;
        }

        /*
        * Nothing happend - this's not a command
        */  
        VkApi::SendMessage($this->peerId, "Боюсь, что такой команды нет.");
    }   
    public function IsCommand(string $command, string $commandSystem) : bool
    {
        return in_array($command, array_keys($this->supportedCommands[$commandSystem]));
    }
    public function IsAdmin(int $userId) : bool
    {
        $response = VkApi::GetChatMembers($this->peerId);
        if($response["error"]) return false;

        foreach($response["response"]["items"] as $user)    
        {
            if($user["member_id"] == $userId)
            {
                $result = $user["is_admin"];
                break;
            }
        }
        return isset($result) & boolval($result);
    }
    public function ChatMemberInfo(int $userId) : array
    {
        $response = VkApi::GetChatMembers($this->peerId);
        if($response["error"]) return array();

        foreach($response["response"]["profiles"] as $user)    
            if($user["id"] == $userId) return $user;
    }

    /*
    * Block of methods for working with DataBases
    */
    //Create new table for that chat if it doesn't exist
    public function CheckForTable()
    {
        $result = $this->db->query("SHOW TABLES;");
        if(!$result) 
        {
            error_log("cannot access the database");
            throw new Exception("cannot access the database");
        }
        
        while($row = $result->fetch_row())
            if($row[0] == $this->tableName) return;
        
        $query = 
            "CREATE TABLE ".$this->tableName." (
            UserID int(1) DEFAULT 0, 
            Warns int(1) DEFAULT 0, 
            Mute int(1) DEFAULT 0, 
            Ban int(1) DEFAULT 0
        );";
        $this->db->query($query);

        //Default properties for the chat
        $this->UpdateUserState(0, "Warns", $this->maxWarns);
        $this->UpdateUserState(0, "Mute", $this->defaultMuteTime);
        $this->UpdateUserState(0, "Ban", $this->defaultBanTime);
    }
    public function GetState(int $userId, string $state)
    {
        $query = "SELECT $state FROM ".$this->tableName." WHERE UserID=$userId;";
        $result = ($this->db->query($query))->fetch_assoc();
        return $result[$state];
    }
    public function UpdateUserState(int $userId, string $state, string $value)
    {
        $result = $this->GetState($userId, $state);

        if(!isset($result))
        {
            $query = 
                "INSERT INTO ".$this->tableName." (UserID, $state)
                VALUES ($userId, $value);";
        }
        else 
        {
            $query = 
                "UPDATE ".$this->tableName."
                SET $state=$value
                WHERE UserID=$userId;";
        }
        $this->db->query($query);
    }

    /*
    * Block of GENERAL commands
    */
    public function Help()
    {
        $message = 
            "Список команд.
            ================= Общее: ==================
            1) /помощь (или /help) - выводит список команд (какая неожиданность).
            2) /настройки (или /settings) [предупреждения | мут | бан] <значение> - устанавливает новое базовое значение для параметра.
            =========== Администрирование: ============
            1) /предупреждение (или /warn) <id пользователя> - добавляет предупреждение пользователю. После достижения максимального количества выдает мут
            2) /мут (или /mute) <id пользователя> <время в минутах> - выдает мут.
            3) /кик (или /kick) <id пользователя> - исключает пользователя.
            4) /бан (или /ban) <id пользователя> <время в минутах> - исключает пользователя и на время запрещает ему войти.";
        VkApi::SendMessage($this->peerId, $message);
    }
    public function SetSettings(?string $prop, $newValue)
    {
        if(!isset($prop))
        {
            VkApi::SendMessage($this->peerId, "И какой параметр мне менять?");
            return;
        }
        if(!isset($newValue))
        {
            VkApi::SendMessage($this->peerId, "Нет значения, которе нужно установить.");
            return;
        }
        if($newValue < 0)
        {
            VkApi::SendMessage($this->peerId, "Недопустимое значение $newValue");
            return;
        }

        switch($prop)
        {
            case "предупреждения":
                $message = "Максимальное количество предупреждений установлено в $newValue.";
                $this->UpdateUserState(0, "Warns", $newValue);
                break;
            case "мут":
                $message = "Стандартное время мута установлено в $newValue минут.";
                $this->UpdateUserState(0, "Mute", $newValue);
                break;
            case "бан":
                $message = "Стандартное время бана установлено в $newValue минут.";
                $this->UpdateUserState(0, "Ban", $newValue);
                break;
            default: 
                $message = "Такого параметра нет. В команде /помощь приведены все возможные параметры. ";
                break;
        }
        VkApi::SendMessage($this->peerId, $message);
    }
    public function LoadSettings()
    {
        $query = "SELECT * FROM ".$this->tableName." WHERE UserID=0;";
        $result = $this->db->query($query)->fetch_assoc();
        $this->maxWarns = $result["Warns"];
        $this->defaultMuteTime = $result["Mute"];
        $this->defaultBanTime = $result["Ban"];
    }

    /*
    * Block of ADMIN commands
    */
    public function GiveWarn(int $userId, string $userName)
    {        
        $warns = $this->GetState($userId, "Warns") ?: 0;
        $warns++;
        $needMute = false;

        if($warns >= $this->maxWarns)
        {
            $needMute = true;
            $warns = 0;
        }

        $this->UpdateUserState($userId, "Warns", $warns);
        
        if($needMute)
        {
            $this->GiveMute($userId, $userName);
            return; 
        }
        
        $message = "Выдано предупреждение пользователю $userName. Предупреждений: $warns/".$this->maxWarns.".";
        VkApi::SendMessage($this->peerId, $message);    
    }
    public function GiveMute(int $userId, string $userName, $time)
    {
        if(!isset($time)) $time = $this->defaultMuteTime;

        if((string)$time != (int)$time)
        {
            VkApi::SendMessage($this->peerId, "Я не совсем понимаю, как можно отправить в мут на \"$time\" времени. Это не число.");
            return;
        }
        $time = (int)$time;

        if($time == 0) 
        {
            $message = "Если $userName когда-то и был в муте, теперь его там нет.";
        }
        else 
        {
            $message = "Пользователь $userName теперь в муте на $time минут.";
        }
        
        $timeInt = (int)strtotime("+$time min");
        $this->UpdateUserState($userId, "Mute", $timeInt);
        VkApi::SendMessage($this->peerId, $message);           
    }
    public function Kick(int $userId, string $userName)
    {
        VkApi::Kick($this->peerId - 2000000000, $userId);
        $message = "$userName был исключен :(";
        VkApi::SendMessage($this->peerId, $message);  
    }
    public function Ban(int $userId, string $userName, $time)
    {
        if(!isset($time)) $time = $this->defaultBanTime;

        if((string)$time != (int)$time)
        {
            VkApi::SendMessage($this->peerId, "Я не совсем понимаю, как можно отправить в мут на \"$time\" времени. Это не число.");
            return;
        }
        $time = (int)$time;
        
        if($time == 0) 
        {
            $message = 
                "Значение времени бана $userName установлено в 0. Это значит, что он точно не забанен.";
        }
        else 
        {
            $message = 
                "Печально признавать, но пользователь $userName теперь забанен на $time минут.";
        }
        $timeInt = (int)strtotime("+$time min");
        $this->UpdateUserState($userId, "Ban", $timeInt);

        VkApi::Kick($this->peerId - 2000000000, $userId);
        VkApi::SendMessage($this->peerId, $message); 
    }
}
?>