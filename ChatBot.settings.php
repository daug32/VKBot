<?php

class ChatBotSettings
{
    public int $maxWarns;
    public int $defaultMuteTime;
    public string $commandMark;

    public function __construct()
    {
        $this->maxWarns = 3;
        $this->defaultMuteTime = 30;
        $this->commandMark = "/";
    }
}

?>