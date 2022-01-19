<?php

require_once("config.php");

class VkApi {
    public static function GetChatMembers($peer_id)
    {
        $response = VkApi::Call(
            "messages.getConversationMembers",
            array(
                "peer_id" => $peer_id,
                "fields" => "member_id"
                )
        );
        return $response;
    }
    public static function KickUser($userId, $chatId)
    {
        //$response;
        // return $response;
    }
    public static function GetUserInfoByScreenName($screenName) 
    {
        $response = VkApi::Call(
            "users.get",
            array("user_ids" => $screenName)
        );
        return $response;
    }
    public static function DeleteConversationMessage($converstaionMessageIDs, $peerId) 
    {
        $response = VkApi::Call(
            "messages.delete",
            array(
                "conversation_message_ids" => $converstaionMessageIDs,
                "delete_for_all" => true,
                "peer_id" => $peerId
            )
        );
        return $response;
    }
    public static function Kick($userId, $chatId) 
    {
        $response = VkApi::Call(
            "messages.removeChatUser",
            array(
                "user_id" => $userId,
                "chat_id" => $chatId
            )
        );
        return $response;
    }
    public static function GetConfirmationCode($groupId) 
    {
        $response = VkApi::Call(
            "groups.getCallbackConfirmationCode", 
            array(
                "group_id" => $groupId
            )
        );
        return $response;
    }
    public static function SendMessage($peerId, $message)
    {
        if(strlen($message) < 1) return -1;
        $response = VkApi::Call(
            "messages.send", 
            array(
                "peer_id" => $peerId,
                "message" => $message
            )
        );
        return $response;
    }
    public static function Call($method, $params = array())
    {
        //set default properties
        $params["access_token"] = VK_API_ACCESS_TOKEN;
        $params["v"] = VK_API_VERSION;
        $params["random_id"] = rand();
    
        $query = http_build_query($params);
        $url = VK_API_END_POINT.$method.'?'.$query;
    
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($curl);
        $error = curl_error($curl);
        if($error) 
        {
            error_log($error);
            throw new Exception("Failed {$method} request");
        }
    
        curl_close($curl);
    
        $response = json_decode($json, true);    
        return $response;
    }   
}
?>