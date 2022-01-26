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
    public array $supportedCommands = 
    [
        "general" => 
        [
            "/помощь" => "Help",
            "/настройки" => "SetSettings",
            "/расписание" => "", 
            "/подписаться" => "", 
            "/отписаться" => ""
        ],
        "admin" => 
        [
            "/предупреждение" => "GiveWarn", 
            "/кик" => "Kick", 
            "/мут" => "GiveMute",
            "/бан" => "Ban"
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
        $peerId = $event["peer_id"];
        $messageId = $event["conversation_message_id"];
        $options = explode(" ", $event["text"]);

        unset($event);

        //If message is not in a personal chat 
        if($peerId != $senderId)
        {
            $this->CheckForTable($peerId);
            $this->LoadSettings($peerId);

            //Return if banned user was invited
            if(isset($action))
            {
                $isAffected = $this->GetRow($peerId, $action["member_id"], "Ban") ?: 0;
                $isAffected = (bool)($isAffected > (int)strtotime("now"));
                if($action["type"] == "chat_invite_user" && $isAffected)
                {
                    VkApi::SendMessage($peerId, "Этот пользователь все еще находится в бане.");
                    VkApi::Kick($peerId - 2000000000, $action["member_id"]);
                    return;
                }
            }

            //Return if banned user was invited
            $isAffected = $this->GetRow($peerId, $senderId, "Mute") ?: 0;
            $isAffected = (bool)($isAffected > (int)strtotime("now"));
            if($isAffected)
            {
                VkApi::DeleteConversationMessage($peerId, $messageId);
                return;
            }
        }
        if($options[0][0] != $this->commandMark[0]) return;
        $command = $options[0];

        //Return if sender isn't admin
        if(!$this->IsAdmin($peerId, $senderId))
        {
            VkApi::SendMessage($peerId, "Вам нужны права администратора для выполнения этой команды.");
            return;
        }

        /*
        * Make preppings for commands without special rights - GENERAL commands
        */
        if($this->IsCommand($command, "general"))
        {
            $command = $this->supportedCommands["general"][$command];
            $this->$command($peerId, $options[1], $options[2]);
            return;
        }

        /*
        * Make preppings for commands WITH special rights - ADMIN commands
        */
        if($this->IsCommand($command, "admin"))
        {
            //Return if message was send in personal chat
            if($senderId == $peerId) 
            {
                VkApi::SendMessage($peerId, "Эта команда не может работать в личке.");
                return;
            }

            //Return if bot isn't chat administartor
            if(!$this->IsAdmin($peerId, -$this->groupId))
            {
                VkApi::SendMessage($peerId, "Я не админ - у меня нет прав выполнять эту команду.");
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
                VkApi::SendMessage($peerId, "Не указан ID пользователя.");
                return;
            }

            //Return if target user is the sender
            if($senderId == $userId)
            {
                VkApi::SendMessage($peerId, "Дурашка! Такие команды нельзя применять на себе.");
                return;
            }

            //Return if user is not a chat member
            $userObject = $this->ChatMemberInfo($peerId, $userId);
            if(!isset($userObject))    
            {
                VkApi::SendMessage($peerId, "В чате не найден пользователь с таким ID.");
                return;
            }
            
            $userName = $userObject["first_name"]." ".$userObject["last_name"];
            $userName = "[id".$userId."|".$userName."]";
            
            //Execute targeted command
            $command = $this->supportedCommands["admin"][$command];
            $this->$command($peerId, $userId, $userName, $options[2]);
            return;
        }

        /*
        * Nothing happend - this's not a command
        */  
        VkApi::SendMessage($peerId, "Боюсь, что такой команды нет.");
    }   
    public function IsCommand($command, $commandSystem)
    {
        return in_array($command, array_keys($this->supportedCommands[$commandSystem]));
    }
    public function IsAdmin($peerId, $userId)
    {
        $response = VkApi::GetChatMembers($peerId);
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
    public function ChatMemberInfo($peerId, $userId)
    {
        $response = VkApi::GetChatMembers($peerId);
        if($response["error"]) return;

        foreach($response["response"]["profiles"] as $user)    
            if($user["id"] == $userId) return $user;
    }

    /*
    * Block of methods for working with DataBases
    */
    //Create new table for that chat if it doesn't exist
    public function CheckForTable($peerId)
    {
        $tableName = "chat_$peerId";
        $result = $this->db->query("SHOW TABLES;");
        if(!$result) 
        {
            error_log("cannot access the database");
            throw new Exception("cannot access the database");
        }
        
        while($row = $result->fetch_row())
            if($row[0] == $tableName) return;
        
        $query = 
            "CREATE TABLE $tableName (
            UserID int(1) DEFAULT 0, 
            Warns int(1) DEFAULT 0, 
            Mute int(1) DEFAULT 0, 
            Ban int(1) DEFAULT 0
        );";
        $this->db->query($query);

        //Default properties for the chat
        $this->UpdateUserState($peerId, 0, "Warns", $this->maxWarns);
        $this->UpdateUserState($peerId, 0, "Mute", $this->defaultMuteTime);
        $this->UpdateUserState($peerId, 0, "Ban", $this->defaultBanTime);
    }
    public function GetRow($peerId, $userId, $state)
    {
        $tableName = "chat_$peerId";
        $query = "SELECT $state FROM $tableName WHERE UserID=$userId;";
        $result = ($this->db->query($query))->fetch_assoc();
        return $result[$state];
    }
    public function UpdateUserState($peerId, $userId, $state, $value)
    {
        $tableName = "chat_$peerId";
        $result = $this->GetRow($peerId, $userId, $state);

        if(!isset($result))
        {
            $query = 
                "INSERT INTO $tableName (UserID, $state)
                VALUES ($userId, $value);";
        }
        else 
        {
            $query = 
                "UPDATE $tableName
                SET $state=$value
                WHERE UserID=$userId;";
        }
        $this->db->query($query);
    }

    /*
    * Block of GENERAL commands
    */
    public function Help($peerId)
    {
        $message = 
            "Список команд.
            ================= Общее: ==================
            1) /помощь - выводит список команд (какая неожиданность).
            2) /настройки [предупреждения | мут | бан] <значение> - устанавливает новое базовое значение для параметра.
            =========== Администрирование: ============
            1) /предупреждение <id пользователя> - добавляет предупреждение пользователю. После достижения максимального количества выдает мут
            2) /мут <id пользователя> <время в минутах> - выдает мут.
            3) /кик <id пользователя> - исключает пользователя.
            4) /бан <id пользователя> <время в минутах> - исключает пользователя и на время запрещает ему войти.";
        VkApi::SendMessage($peerId, $message);
    }
    public function SetSettings($peerId, $prop, $newValue)
    {
        if(!isset($prop))
        {
            VkApi::SendMessage($peerId, "И какой параметр мне менять?");
            return;
        }
        if(!isset($newValue))
        {
            VkApi::SendMessage($peerId, "Нет значения, которе нужно установить.");
            return;
        }
        $newValue = (int)$newValue;
        if($newValue < 0)
        {
            VkApi::SendMessage($peerId, "Недопустимое значение $newValue");
            return;
        }

        switch($prop)
        {
            case "предупреждения":
                $message = "Максимальное количество предупреждений установлено в $newValue.";
                $this->UpdateUserState($peerId, 0, "Warns", $newValue);
                break;
            case "мут":
                $message = "Стандартное время мута установлено в $newValue минут.";
                $this->UpdateUserState($peerId, 0, "Mute", $newValue);
                break;
            case "бан":
                $message = "Стандартное время бана установлено в $newValue минут.";
                $this->UpdateUserState($peerId, 0, "Ban", $newValue);
                break;
            default: 
                $message = "Такого параметра нет. В команде /помощь приведены все возможные параметры. ";
                break;
        }
        VkApi::SendMessage($peerId, $message);
    }
    public function LoadSettings($peerId)
    {
        $query = "SELECT * FROM chat_$peerId WHERE UserID=0;";
        $result = $this->db->query($query)->fetch_assoc();
        $this->maxWarns = $result["Warns"];
        $this->defaultMuteTime = $result["Mute"];
        $this->defaultBanTime = $result["Ban"];
    }

    /*
    * Block of ADMIN commands
    */
    public function GiveWarn($peerId, $userId, $userName)
    {        
        $warns = $this->GetRow($peerId, $userId, "Warns") ?: 0;
        $warns++;
        $needMute = false;

        if($warns >= $this->maxWarns)
        {
            $needMute = true;
            $warns = 0;
        }

        $this->UpdateUserState($peerId, $userId, "Warns", $warns);
        
        if($needMute)
        {
            $this->GiveMute($peerId, $userId, $userName, $this->defaultMuteTime);
            return; 
        }
        
        $message = "Выдано предупреждение пользователю $userName. Предупреждений: $warns/".$this->maxWarns.".";
        VkApi::SendMessage($peerId, $message);    
    }
    public function GiveMute($peerId, $userId, $userName, $time)
    {
        if(!isset($time)) $time = $this->defaultMuteTime;

        if($time == 0) 
        {
            $message = 
                "Если $userName когда-то и был в муте, теперь его там нет.";
        }
        else 
        {
            $time = (int)$time; //make sure it's a number
            $message = "Пользователь $userName теперь в муте на $time минут.";
        }
        
        $timeInt = (int)strtotime("+$time min");
        $this->UpdateUserState($peerId, $userId, "Mute", $timeInt);
        VkApi::SendMessage($peerId, $message);           
    }
    public function Kick($peerId, $userId, $userName)
    {
        VkApi::Kick($peerId - 2000000000, $userId);
        $message = "$userName был исключен :(";
        VkApi::SendMessage($peerId, $message);  
    }
    public function Ban($peerId, $userId, $userName, $time)
    {
        if(!isset($time)) $time = $this->defaultBanTime;
        
        if($time == 0) 
        {
            $message = 
                "Значение времени бана $userName установлено в 0. Это значит, что он точно не забанен.";
        }
        else 
        {
            $time = (int)$time; //make sure it's a number
            $message = "Печально признавать, но пользователь $userName теперь забанен на $time минут.";
        }
        $timeInt = (int)strtotime("+$time min");
        $this->UpdateUserState($peerId, $userId, "Ban", $timeInt);

        VkApi::Kick($peerId - 2000000000, $userId);
        VkApi::SendMessage($peerId, $message); 
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

