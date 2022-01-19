<?php

require_once("vk_api.php");
require_once("ChatBot.settings.php");
class ChatBot
{
    public mysqli $db;
    public ChatBotSettings $settings; 

    function __construct($settings, $database)
    {
        $this->db = $database;
        $this->settings = $settings;
    }
    function __destruct()
    {
        $this->db->close();
    }

    //Create new table for that chat if it doesn't exist
    public function CheckForTable($peerId)
    {
        $peerId = "chat_".$peerId;
        $result = $this->db->query("SHOW TABLES;");
        if(!$result) {
            error_log("cannot access the database");
            throw new Exception("cannot access the database");
        }
        $result = $result->fetch_row();

        //return if table exist
        if(isset($result) && in_array($peerId, $result)) return true;
        
        $query = "CREATE TABLE $peerId (UserID int(1), Warns int(1), Mute date, Ban date);";
        $result = $this->db->query($query);
        return true;
    }
    public function DeleteTable($peerIdd)
    {
        $query = "DROP TABLE chat_$peerId";
        $result = $this->db->query($query);
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
            $query = "
                INSERT INTO chat_$chatId (UserID, Warns) 
                VALUES ($userId, 1);";
        //if user is in table, update his info
        else
        {
            $warns = $result["Warns"] + 1;
            if($warns >= $this->settings->maxWarns)
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
            $this->GiveMute($userId, $userName, $chatId, $this->settings->defaultMuteTime);
            return; 
        }
        
        $message = "Выдано предупреждение пользователю $userName. Предупреждений: $warns/".$this->settings->maxWarns.".";
        VkApi::SendMessage($chatId, $message);    
    }
    public function GiveMute($userId, $userName, $chatId, $time)
    {
        if(!isset($time)) $time = $this->settings->defaultMuteTime;
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

