# VkBot 
What's it? A bot for Vkontakte, of course.<br>
## How to use?
<ul>
<li>First, you need to set up your own server 😔 That's needed to allow VK send you requests and notifications about current situation in your conversation.</li>
<li>Second, create VK group and mark it as a bot in settings. Also, make a token key and write server's domain.</li>
<li>Third, config.php file have to be dealt with. Write there your a) ID of VK profile, b) community ID and c) its token.</li>
<li>Fourth, run server and add bot into conversation. (just for sure: index.php routes VK events, creates an object of ChatBot class and calls its Execute method with required variables.)</li>
<li>And the last part is about to learn commands. Write "/помощь" into the conversation to get a list of available commands.</li>
</ul>
