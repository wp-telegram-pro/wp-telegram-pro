<?php
/**
 * Telegram Bot API
 * @Author: Parsa Kafi
 * @WebSite: http://parsa.ws
 */

if (class_exists('TelegramWPTB'))
    return;

class TelegramWPTP
{
    protected $token, $input, $last_result = '';
    protected $fileMethod = array('sendPhoto', 'sendAudio', 'sendDocument', 'sendVideo', 'sendVoice', 'sendVideoNote');
    protected $textMethod = array('sendMessage', 'editMessageText', 'InputTextMessageContent');
    public $disable_web_page_preview = false;
    
    function __construct($token)
    {
        $this->token = $token;
    }
    
    public function set_token($token)
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
        if (empty($this->token) || !function_exists('curl_init'))
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
        
        if (in_array($method, $this->textMethod))
            $parameter['disable_web_page_preview'] = $this->disable_web_page_preview;
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (count($parameter))
            curl_setopt($ch, CURLOPT_POSTFIELDS, $parameter);
        $result = $this->last_result = curl_exec($ch);
        return $result;
    }
    
    function setWebhook($url)
    {
        $result = $this->last_result = $this->request('setWebhook', array('url' => $url));
        if (!$result) return false;
        $json = json_decode($result, true);
        return isset($json['result']) ? $json['result'] : false;
    }
    
    function sendMessage($message, $keyboard = null, $chat_id = null, $parse_mode = null)
    {
        $chat_id = $chat_id == null ? $this->input['chat_id'] : $chat_id;
        $parameter = array('chat_id' => $chat_id, 'text' => $message);
        if ($keyboard != null)
            $parameter['reply_markup'] = $keyboard;
        if ($parse_mode != null)
            $parameter['parse_mode'] = $parse_mode;
        return $this->last_result = $this->request('sendMessage', $parameter);
    }
    
    function editMessageText($message, $message_id, $keyboard = null, $chat_id = null, $parse_mode = null)
    {
        $chat_id = $chat_id == null ? $this->input['chat_id'] : $chat_id;
        $parameter = array('chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $message);
        if ($keyboard != null)
            $parameter['reply_markup'] = $keyboard;
        if ($parse_mode != null)
            $parameter['parse_mode'] = $parse_mode;
        return $this->last_result = $this->request('editMessageText', $parameter);
    }
    
    function sendFile($method, $file, $caption = null, $keyboard = null, $chat_id = null)
    {
        $chat_id = $chat_id == null ? $this->input['chat_id'] : $chat_id;
        $parameter = array('chat_id' => $chat_id, 'file' => $file);
        if ($caption != null)
            $parameter['caption'] = mb_substr($caption, 0, 200);
        if ($keyboard != null)
            $parameter['reply_markup'] = $keyboard;
        return $this->last_result = $this->request($method, $parameter);
    }
    
    function answerCallbackQuery($text, $callback_query_id = null, $show_alert = false)
    {
        $callback_query_id = $callback_query_id == null ? $this->input['callback_query_id'] : $callback_query_id;
        $parameter = array('callback_query_id' => $callback_query_id, 'text' => $text, 'show_alert' => $show_alert);
        return $this->last_result = $this->request('answerCallbackQuery', $parameter);
    }
    
    function editMessageReplyMarkup($reply_markup, $message_id = null, $chat_id = null)
    {
        $chat_id = $chat_id == null ? $this->input['chat_id'] : $chat_id;
        $message_id = $message_id == null ? $this->input['message_id'] : $message_id;
        $parameter = array('reply_markup' => $reply_markup, 'chat_id' => $chat_id, 'message_id' => $message_id);
        return $this->last_result = $this->request('editMessageReplyMarkup', $parameter);
    }
    
    function bot_info()
    {
        return $this->last_result = $this->request('getMe');
    }
    
    function get_members_count($chat_id)
    {
        if ($chat_id == null || $chat_id == '')
            return false;
        $parameter = array('chat_id' => $chat_id);
        return $this->last_result = $this->request('getChatMembersCount', $parameter);
    }
    
    function keyboard($keys, $type = 'keyboard')
    {
        if (!is_array($keys)) return '';
        //if ($type == 'keyboard')
        //    $keys = array_map('strval', $keys);
        //$keyboard = $type == 'keyboard' ? array($keys) : $keys;
        $keyboard = $keys;
        $reply = array($type => $keyboard);
        if ($type == 'keyboard')
            $reply['resize_keyboard'] = true;
        return json_encode($reply, true);
    }
    
    function get_last_result($raw = false)
    {
        if ($raw)
            return $this->last_result;
        if (!$this->last_result)
            return array();
        $data = json_decode($this->last_result, true);
        if (JSON_ERROR_NONE !== json_last_error())
            return false;
        return $data === null ? array() : $data;
    }
    
    function disable_web_page_preview($status)
    {
        $this->disable_web_page_preview = $status;
    }
}