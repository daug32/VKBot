    <?php

    //One command - one message
    //Every cammand starts with '/' symbol

    define("SYNTAX_ANALIZER_NOT_COMMAND", 0);
    define("SYNTAX_ANALIZER_INVALID_COMMAND", 1);
    
    // /pасписание.установить.понедельник.1.лекция.математика.13:30.15:15
    // /расписание.удалить.понедельник.

    require_once("vk_api.php");

    class Bot 
    {
        public $supportedCommands = 
        [
            "general" => 
            [
                "/помощь" => "Help"
            ],
            "admin" => 
            [
                "/предупреждение" => "GiveWarn", 
                "/кик" => "Kick", 
                "/мут" => "GiveMute"
            ],
            "postman" => 
            [
                "/расписание" => "", 
                "/подписаться" => "", 
                "/отписаться" => ""
            ]
        ];

        public function GetWordsIfCommand($message, $separator = " ") 
        {
            if(strlen($message) < 1 || $message[0] != "/") return;
            $words = explode($separator, str_replace("  ", "", $message));
            return $words;
        }
        public function FormatId($id)
        {
            return str_replace(["[", "@", "]"], "", explode("|", $id)[0]);
        }
        public function ExecuteCommand($senderId, $peerId, $options) 
        {
            $command = $options[0];
            
            if(in_array($command, array_keys($this->supportedCommands["general"])))
            {
                $command = $this->supportedCommands["general"][$command];
                $this->$command($peerId);
                return;
            }

            if(in_array($command, array_keys($this->supportedCommands["admin"])))
            {
                //return if message was send in personal chat
                if($senderId == $peerId) 
                {
                    VkApi::SendMessage($peerId, "Эта команда не может работать в личке.");
                    return;
                }

                //format id
                $userId = $options[1];
                if(!isset($userId)) 
                {
                    VkApi::SendMessage($peerId, "Не указан ID пользователя.");
                    return;
                }
                $userId = $this->FormatId($userId);

                //get user info
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
                
                $command = $this->supportedCommands["admin"][$command];
                $this->$command($userId, $userName, $peerId, $options[2]);
                return;
            }

            VkApi::SendMessage($peerId, "Боюсь, что такой команды нет.");
        }
        //general commands 
        public function Help($chatId)
        {
            $message = 
                "Список команд. Администрирование.\n".
                "=== 1) /предупреждение <id пользователя>\n".
                "=== 2) /мут <id пользователя> <время в минутах>\n".
                "=== 3) /кик <id пользователя>\n";
            VkApi::SendMessage($chatId, $message);
        }
        //admin commands
        public function GiveWarn($userId, $userName, $chatId)
        {
            $message = $userName.", Вам выдано предупреждение.";
            VkApi::SendMessage($chatId, $message);        
        }
        public function GiveMute($userId, $userName, $chatId, $time)
        {
            $message = "Пользователь ".$userName." теперь в муте на ".$time." минут.";
            VkApi::SendMessage($chatId, $message);           
        }
        public function Kick($userId, $userName, $chatId)
        {
            $message = $userName." был исключен :(";
            VkApi::SendMessage($chatId, $message);  
        }
    }
    ?>

