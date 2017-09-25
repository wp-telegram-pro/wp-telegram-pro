<?php

class TelegramWCTB
{

    protected $token;
    protected $const_;

    function __construct($token, $const_)
    {
        $this->token = $token;
        $this->const_ = $const_;
    }

    function run($method, $parameter = array())
    {
        if (empty($this->token))
            return false;

        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method . '?' . http_build_query($parameter);
        return file_get_contents($url);
    }

    function setWebhook()
    {
        $result = $this->run('setWebhook', array('url' => get_bloginfo('url') . '/'));
        if (!$result)
            return false;
        $json = json_decode($result, true);
        return $json['result'];
    }

    function sendMessage($chat_id, $message, $replyMarkup)
    {
        $api = 'https://api.telegram.org/bot' . $this->token;
        file_get_contents($api . '/sendMessage?chat_id=' . $chat_id . '&text=' . urlencode($message) . '&reply_markup=' . $replyMarkup);
    }

    function keyboard($type = 'keyboard')
    {
        $reply = array();
        $keyboard = array(array($this->const_['next'], $this->const_['prev'], $this->const_['back']));
        $reply['keyboard'] = $keyboard;
        $reply['resize_keyboard'] = true;
        return json_encode($reply, true);
    }
}