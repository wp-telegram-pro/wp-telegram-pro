<?php
/**
 * Telegram Bot API
 * @Author: Parsa Kafi
 * @WebSite: http://parsa.ws
 */

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
if ( class_exists( 'TelegramWPTP' ) )
	return;

class TelegramWPTP {
	protected $token, $input, $last_result = '', $raw_response, $valid_json, $decoded_body, $response_code, $response_message, $body, $headers, $file, $file_key, $disable_web_page_preview = false;
	protected $fileMethod = array(
		'sendPhoto',
		'sendAudio',
		'sendDocument',
		'sendVideo',
		'sendVoice',
		'sendVideoNote'
	);
	protected $textMethod = array( 'sendMessage', 'editMessageText', 'InputTextMessageContent' );

	function __construct( $token ) {
		$this->token = $token;
		add_filter( 'wptelegrampro_api_request_parameters', [ $this, 'request_file_parameter' ] );
		add_action( 'http_api_curl', [ $this, 'modify_http_api_curl' ], 10, 3 );
	}

	function input() {
		$input = file_get_contents( 'php://input' );
		if ( $input ) {
			$data = json_decode( $input, true );
			if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['update_id'] ) )
				throw new \Exception( 'Json With Error!' );
			$return               = array();
			$return['input']      = $data;
			$return['text']       = $data['message']['text'];
			$return['from']       = $data['message']['from'];
			$return['chat_id']    = $data['message']['from']['id'];
			$return['message_id'] = $data['message']['message_id'];
			if ( isset( $data['callback_query'] ) ) {
				$callback_query              = $data['callback_query'];
				$return['text']              = $callback_query['message']['text'];
				$return['from']              = $callback_query['from'];
				$return['chat_id']           = $callback_query['from']['id'];
				$return['message_id']        = $callback_query['message']['message_id'];
				$return['data']              = $callback_query['data'];
				$return['callback_query_id'] = $callback_query['id'];
			}
			$this->input = $return;

			return $return;
		} else {
			throw new \Exception( 'Not Receive Telegram Input!' );
		}
	}

	function request( $method, $parameter = array() ) {
		$parameter['disable_web_page_preview'] = $this->disable_web_page_preview;
		$proxy_status                          = apply_filters( 'wptelegrampro_proxy_status', '' );
		$url                                   = 'https://api.telegram.org/bot' . $this->token . '/' . $method;

		$parameter = apply_filters( 'wptelegrampro_telegram_bot_api_parameters', $parameter );
		$url       = apply_filters( 'wptelegrampro_api_request_url', $url );

		if ( ! empty( $proxy_status ) ) {
			$headers = array( 'wptelegrampro' => true, 'Content-Type' => 'application/json' );

			if ( in_array( $method, $this->fileMethod ) && isset( $parameter['file'] ) ) {
				$key = $this->file_key = strtolower( str_replace( array( 'send', 'VideoNote' ),
					array( '', 'video_note' ), $method ) );

				if ( filter_var( $parameter['file'], FILTER_VALIDATE_URL ) ) { // is url
					$parameter[ $key ] = $parameter['file'];
					remove_action( 'http_api_curl', [ $this, 'modify_http_api_curl' ] );

				} else {
					$parameter[ $key ]       = $this->file = $parameter['file'];
					$headers['attache_file'] = true;
					add_action( 'http_api_curl', [ $this, 'modify_http_api_curl' ], 99999, 3 );
				}
				unset( $parameter['file'] );
			} else {
				remove_action( 'http_api_curl', [ $this, 'modify_http_api_curl' ] );
			}

			// $parameter = apply_filters('wptelegrampro_api_request_parameters', $parameter);
			$parameter = $this->request_file_parameter( $parameter );

			$args = array(
				'timeout'   => 30, //seconds
				'blocking'  => true,
				'headers'   => $headers,
				'body'      => $parameter,
				'sslverify' => true,
			);

			foreach ( $args as $argument => $value ) {
				$args[ $argument ] = apply_filters( "wptelegrampro_api_request_arg_{$argument}", $value );
			}

			$args         = apply_filters( 'wptelegrampro_api_remote_post_args', $args, $method, $this->token );
			$raw_response = $this->raw_response = $this->last_result = wp_remote_post( $url, $args );
			$this->set_properties( $raw_response );
			$this->valid_json = $this->decode_body();
			$result           = $this->get_decoded_body();

		} else {
			if ( empty( $this->token ) || ! function_exists( 'curl_init' ) )
				return false;

			$ch = curl_init();
			if ( in_array( $method, $this->fileMethod ) && isset( $parameter['file'] ) ) {
				$key = strtolower( str_replace( array( 'send', 'VideoNote' ), array( '', 'video_note' ), $method ) );
				if ( filter_var( $parameter['file'], FILTER_VALIDATE_URL ) ) {
					$parameter[ $key ] = $parameter['file'];
				} else {
					$parameter[ $key ] = new \CURLFile( realpath( $parameter['file'] ) );
					curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
						"Content-Type: multipart/form-data"
					) );
				}
			}

			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			if ( count( $parameter ) )
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $parameter );
			$this->last_result = $this->body = curl_exec( $ch );
			$this->valid_json  = $this->decode_body();
			$result            = $this->get_decoded_body();
		}

		return $result;
	}

	public function set_token( $token ) {
		$this->token = $token;
	}

	function request_file_parameter( $parameter ) {
		$image_send_mode = apply_filters( 'wptelegrampro_image_send_mode', 'image_path' );
		if ( $this->file && $image_send_mode === 'image_path' ) {
			$parameter[ $this->file_key ] = new \CURLFile( realpath( $this->file ) );
		}

		return $parameter;
	}

	function modify_http_api_curl( &$handle, $r, $url ) {
		$image_send_mode = apply_filters( 'wptelegrampro_image_send_mode', 'image_path' );
		if ( ! isset( $r['headers']['attache_file'] ) || $image_send_mode !== 'image_path' )
			return;
		if ( $this->check_remote_post( $r, $url ) ) {
			curl_setopt( $handle, CURLOPT_HTTPHEADER, array( "Content-Type:multipart/form-data" ) );
		}
	}

	function setWebhook( $url ) {
		$result = $this->last_result = $this->request( 'setWebhook', array( 'url' => $url ) );
		if ( ! $result )
			return false;

		return isset( $result['result'] ) && ( $result['description'] === 'Webhook was set' || $result['description'] === 'Webhook is already set' );
	}

	function getWebhook() {
		$result = $this->last_result = $this->request( 'getWebhookInfo' );
		if ( ! $result )
			return false;

		return isset( $result['result'] ) ? $result['result'] : false;
	}

	function deleteMessage( $message_id, $chat_id = null ) {
		$chat_id   = $chat_id == null ? $this->input['chat_id'] : $chat_id;
		$parameter = array( 'chat_id' => $chat_id, 'message_id' => $message_id );

		return $this->last_result = $this->request( 'deleteMessage', $parameter );
	}

	function sendMessage( $message, $keyboard = null, $chat_id = null, $parse_mode = null ) {
		$chat_id   = $chat_id == null ? $this->input['chat_id'] : $chat_id;
		$parameter = array( 'chat_id' => $chat_id, 'text' => $message );
		if ( $keyboard != null )
			$parameter['reply_markup'] = $keyboard;
		if ( $parse_mode != null )
			$parameter['parse_mode'] = $parse_mode;

		return $this->last_result = $this->request( 'sendMessage', $parameter );
	}

	function editMessageText( $message, $message_id, $keyboard = null, $chat_id = null, $parse_mode = null ) {
		$chat_id   = $chat_id == null ? $this->input['chat_id'] : $chat_id;
		$parameter = array( 'chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $message );
		if ( $keyboard != null )
			$parameter['reply_markup'] = $keyboard;
		if ( $parse_mode != null )
			$parameter['parse_mode'] = $parse_mode;

		return $this->last_result = $this->request( 'editMessageText', $parameter );
	}

	function sendFile( $method, $file, $caption = null, $keyboard = null, $chat_id = null, $parse_mode = null ) {
		$chat_id   = $chat_id == null ? $this->input['chat_id'] : $chat_id;
		$parameter = array( 'chat_id' => $chat_id, 'file' => $file );
		if ( $caption != null )
			$parameter['caption'] = $caption;
		if ( $keyboard != null )
			$parameter['reply_markup'] = $keyboard;
		if ( $parse_mode != null )
			$parameter['parse_mode'] = $parse_mode;

		return $this->last_result = $this->request( $method, $parameter );
	}

	function answerCallbackQuery( $text, $callback_query_id = null, $show_alert = false ) {
		$callback_query_id = $callback_query_id == null ? $this->input['callback_query_id'] : $callback_query_id;
		$parameter         = array(
			'callback_query_id' => $callback_query_id,
			'text'              => $text,
			'show_alert'        => $show_alert
		);

		return $this->last_result = $this->request( 'answerCallbackQuery', $parameter );
	}

	function editMessageReplyMarkup( $reply_markup, $message_id = null, $chat_id = null ) {
		$chat_id    = $chat_id == null ? $this->input['chat_id'] : $chat_id;
		$message_id = $message_id == null ? $this->input['message_id'] : $message_id;
		$parameter  = array( 'reply_markup' => $reply_markup, 'chat_id' => $chat_id, 'message_id' => $message_id );

		return $this->last_result = $this->request( 'editMessageReplyMarkup', $parameter );
	}

	function bot_info() {
		return $this->last_result = $this->request( 'getMe' );
	}

	function get_members_count( $chat_id ) {
		if ( $chat_id == null || $chat_id == '' )
			return false;
		$parameter = array( 'chat_id' => $chat_id );

		return $this->last_result = $this->request( 'getChatMembersCount', $parameter );
	}

	function keyboard( $keys, $type = 'keyboard' ) {
		if ( ! is_array( $keys ) )
			return '';
		//if ($type == 'keyboard')
		//    $keys = array_map('strval', $keys);
		//$keyboard = $type == 'keyboard' ? array($keys) : $keys;
		$keyboard = $keys;
		$reply    = array( $type => $keyboard );
		if ( $type == 'keyboard' )
			$reply['resize_keyboard'] = true;

		return json_encode( $reply, true );
	}

	function get_last_result( $raw = false ) {
		if ( $raw )
			return $this->last_result;

		if ( ! $this->last_result )
			return array();

		if ( $this->valid_json )
			return $this->decoded_body;
		else
			return false;
	}

	/**
	 * Converts raw API response to proper decoded response.
	 * @since   1.0.0
	 */
	public function decode_body() {
		$this->decoded_body = json_decode( $this->body, true );
		// check for PHP < 5.3
		if ( function_exists( 'json_last_error' ) && defined( 'JSON_ERROR_NONE' ) )
			return ( json_last_error() == JSON_ERROR_NONE );

		return true;
	}

	/**
	 * Sets the class properties
	 * Copy from "WP Telegram" plugin
	 *
	 * @param $raw_response string wp remote response
	 */
	public function set_properties( $raw_response ) {
		$properties = array(
			'response_code',
			'response_message',
			'body',
			'headers',
		);
		foreach ( $properties as $property ) {
			$this->$property = call_user_func( 'wp_remote_retrieve_' . $property, $raw_response );
		}
	}

	/**
	 * Gets the original HTTP response.
	 * @return array
	 * @since   1.0.0
	 *
	 */
	public function get_raw_response() {
		return $this->raw_response;
	}

	/**
	 * Gets the HTTP response code.
	 * @return null|int
	 * @since   1.0.0
	 *
	 */
	public function get_response_code() {
		return $this->response_code;
	}

	/**
	 * Returns the value of valid_json
	 * @return bool
	 * @since   1.0.0
	 *
	 */
	public function is_valid_json() {
		return $this->valid_json;
	}

	/**
	 * Gets the HTTP response message.
	 * @return null|string
	 * @since   1.0.0
	 *
	 */
	public function get_response_message() {
		return $this->response_message;
	}

	/**
	 * Return the HTTP headers for this response.
	 * @return array
	 * @since   1.0.0
	 *
	 */
	public function get_headers() {
		return $this->headers;
	}

	/**
	 * Return the raw body response.
	 * @return string
	 * @since   1.0.0
	 *
	 */
	public function get_body() {
		return $this->body;
	}

	/**
	 * Return the decoded body response.
	 * @return array
	 * @since   1.0.0
	 *
	 */
	public function get_decoded_body() {
		return $this->decoded_body;
	}

	/**
	 * Helper function to return the payload of a successful response.
	 * @return mixed
	 * @since   1.0.0
	 *
	 */
	public function get_result() {
		return $this->decoded_body['result'];
	}

	function disable_web_page_preview( $status ) {
		$this->disable_web_page_preview = $status;
	}
}