<?php
/**
 * Rest class
 *
 * @since 1.0
 */

namespace wptelegrampro;

defined('ABSPATH') || exit;

class REST extends Instance
{
    protected static $_instance;

    /**
     * Init
     *
     * @since  1.0
     * @access public
     */
    public function init()
    {
        add_action('rest_api_init', [$this, 'rest_api_init']);
    }

    /**
     * Register REST hooks
     *
     * @since  1.0
     * @access public
     */
    public function rest_api_init()
    {
        register_rest_route('wptelegrampro/v1', '/telegram_bot_auth', array(
            'methods' => 'POST',
            'callback' => [$this, 'telegramBotAuth'],
        ));
    }

    /**
     * Send SMS
     */
    public function telegramBotAuth()
    {
        return Users::get_instance()->telegramBotTFA();
    }

    /**
     * Return content
     *
     * @param array $data
     * @return array
     */
    public static function ok($data)
    {
        $data['_res'] = true;
        return $data;
    }

    /**
     * Return error
     *
     * @param string $message Error Message
     * @return array
     */
    public static function err($message)
    {
        return array('_res' => false, '_msg' => $message);
    }
}
