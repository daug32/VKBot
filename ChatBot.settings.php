<?php

class ChatBotSettings
{
    public int $maxWarns;
    public int $defaultMuteTime;

    public function __construct(
        $maxWarns = 3, 
        $defaultMuteTime = 30) 
    {
        $this->maxWarns = $maxWarns;
        $this->defaultMuteTime = $defaultMuteTime;
    }
}

?>