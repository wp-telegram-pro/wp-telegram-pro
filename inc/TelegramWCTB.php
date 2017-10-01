<?php
/**
 * Telegram Bot API Class
 * @Author: Parsa Kafi
 * @WebSite: http://parsa.ws
*/

class TelegramWCTB
{
    protected $token, $input;
    protected $fileMethod = array('sendPhoto', 'sendAudio', 'sendDocument', 'sendVideo', 'sendVoice', 'sendVideoNote');

    function __construct($token)
    {
        $this->token = $token;
    }

    function input()
    {
        $input = file_get_contents('php://input');
        if ($input) {
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['update_id']))
                throw new Exception('Json With Error!');
            $return = array();
            $return['input'] = $data;
            $return['text'] = $data['message']['text'];
            $return['from'] = $data['message']['from'];
            $return['chat_id'] = $data['message']['from']['id'];
            $return['message_id'] = $data['message']['message_id'];
            if (isset($data['callback_query'])) {
                $callback_query = $data['callback_query'];
                $return['text'] = $callback_query['message']['text'];
                $return['from'] = $callback_query['from'];
                $return['chat_id'] = $callback_query['from']['id'];
                $return['message_id'] = $callback_query['message']['message_id'];
                $return['data'] = $callback_query['data'];
                $return['callback_query_id'] = $callback_query['id'];
            }
            $this->input = $return;
            return $return;
        } else {
            throw new Exception('Not Receive Telegram Input!');
        }
    }

    function request($method, $parameter = array())
    {
        if (empty($this->token) && !function_exists('curl_init'))
            return false;
        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;
        $ch = curl_init();
        if (in_array($method, $this->fileMethod) && isset($parameter['file'])) {
            $key = strtolower(str_replace(array('send', 'VideoNote'), array('', 'video_note'), $method));
            $parameter[$key] = new CURLFile(realpath($parameter['file']));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type:multipart/form-data"
            ));
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (count($parameter))
            curl_setopt($ch, CURLOPT_POSTFIELDS, $parameter);
        return curl_exec($ch);
    }

    function setWebhook($url)
    {
        $result = $this->request('setWebhook', array('url' => $url));
        if (!$result)
            return false;
        $json = json_decode($result, true);
        return $json['result'];
    }

    function sendMessage($message, $keyboard = null, $chat_id = null, $parse_mode = null)
    {
        $chat_id = $chat_id == null ? $this->input['chat_id'] : $chat_id;
        $parameter = array('chat_id' => $chat_id, 'text' => $message);
        if ($keyboard != null)
            $parameter['reply_markup'] = $keyboard;
        if ($parse_mode != null)
            $parameter['parse_mode'] = $parse_mode;
        return $this->request('sendMessage', $parameter);
    }

    function sendFile($method, $file, $caption = null, $keyboard = null, $chat_id = null)
    {
        $chat_id = $chat_id == null ? $this->input['chat_id'] : $chat_id;
        $parameter = array('chat_id' => $chat_id, 'file' => $file);
        if ($caption != null)
            $parameter['caption'] = mb_substr($caption, 0, 200);
        if ($keyboard != null)
            $parameter['reply_markup'] = $keyboard;
        return $this->request($method, $parameter);
    }

    function answerCallbackQuery($text, $callback_query_id = null, $show_alert = false)
    {
        $callback_query_id = $callback_query_id == null ? $this->input['callback_query_id'] : $callback_query_id;
        $parameter = array('callback_query_id' => $callback_query_id, 'text' => $text, 'show_alert' => $show_alert);
        return $this->request('answerCallbackQuery', $parameter);
    }

    function editMessageReplyMarkup($reply_markup, $message_id = null, $chat_id = null)
    {
        $chat_id = $chat_id == null ? $this->input['chat_id'] : $chat_id;
        $message_id = $message_id == null ? $this->input['message_id'] : $message_id;
        $parameter = array('reply_markup' => $reply_markup, 'chat_id' => $chat_id, 'message_id' => $message_id);
        return $this->request('editMessageReplyMarkup', $parameter);
    }

    function bot_info()
    {
        return $this->request('getMe');
    }

    function keyboard($keys, $type = 'keyboard')
    {
        if (!is_array($keys))
            return '';
        if ($type == 'keyboard')
            $keys = array_map('strval', $keys);
        $keyboard = $type == 'keyboard' ? array($keys) : $keys;
        $reply = array($type => $keyboard);
        if ($type == 'keyboard')
            $reply['resize_keyboard'] = true;
        return json_encode($reply, true);
    }
}