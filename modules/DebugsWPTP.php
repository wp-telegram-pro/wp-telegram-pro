<?php

class DebugsWPTP extends WPTelegramPro
{
    public static $instance = null;
    protected $page_key, $page_title;
    
    public function __construct()
    {
        parent::__construct(true);
        $this->page_key = $this->plugin_key . '-debugs';
        $this->page_title = __('Debugs', $this->plugin_key);
        add_action('admin_menu', array($this, 'menu'), 999999);
    }
    
    function menu()
    {
        add_submenu_page($this->plugin_key, $this->plugin_name . $this->page_title_divider . $this->page_title, $this->page_title, 'manage_options', $this->page_key, array($this, 'pageContent'));
    }
    
    function pageContent()
    {
        global $wp_version, $wpdb;
        
        $phpversion = phpversion();
        $curl = function_exists('curl_version') ? curl_version()['version'] : $this->words['inactive'];
        
        $debug = defined('WP_DEBUG') ? WP_DEBUG : false;
        $debugMode = $debug ? $this->words['active'] : $this->words['inactive'];
        $ssl = is_ssl() ? $this->words['active'] : $this->words['inactive'];
        $locale = get_locale();
        $isRTL = is_rtl() ? $this->words['yes'] : $this->words['no'];
        
        $checkDBTable = $wpdb->get_var("show tables like '$this->db_table'") === $this->db_table;
        $checkDBTable = $checkDBTable ? $this->words['yes'] : $this->words['no'];
        
        $debugs = array(
            'PHP' => array(
                __('PHP Version', $this->plugin_key) => $phpversion,
                __('PHP CURL', $this->plugin_key) => $curl
            ),
            __('WordPress') => array(
                __('WordPress Version', $this->plugin_key) => $wp_version,
                __('WordPress Debugging Mode', $this->plugin_key) => $debugMode,
                __('WordPress Address (URL)', $this->plugin_key) => get_bloginfo('siteurl'),
                __('Locale', $this->plugin_key) => $locale,
                __('Is RTL', $this->plugin_key) => $isRTL,
                __('SSL', $this->plugin_key) => $ssl
            ),
            $this->plugin_name => array(
                __('Plugin Version', $this->plugin_key) => WPTELEGRAMPRO_VERSION,
                __('Plugin DB Table Created', $this->plugin_key) => $checkDBTable
            )
        );
        
        self::dd($this->checkSSL('parsa.ws'));
        ?>
        <div class="wrap wptp-wrap">
            <h1 class="wp-heading-inline"><?php echo $this->plugin_name . $this->page_title_divider . $this->page_title ?></h1>
            <table>
                <?php
                foreach ($debugs as $key => $debug) {
                    echo '<tr><th colspan="2">' . $key . '</th></tr>';
                    foreach ($debug as $title => $value) {
                        echo '<tr><td>' . $title . '</td><td>' . $value . '</td></tr>';
                    }
                }
                ?>
            </table>
        </div>
        <?php
    }
    
    function checkSSL($url)
    {
        $timeout = 60;
        $streamContext = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
            ],
        ]);
        $client = stream_socket_client(
            "ssl://{$url}:443",
            $errorNumber,
            $errorDescription,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $streamContext);
        
        $response = stream_context_get_params($client);
        
        $certificateProperties = openssl_x509_parse($response['options']['ssl']['peer_certificate']);
        return $certificateProperties;
    }
    
    public static function dd($data)
    {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
    }
    
    /**
     * Returns an instance of class
     * @return  DebugsWPTP
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new DebugsWPTP();
        return self::$instance;
    }
}

$DebugsWPTP = DebugsWPTP::getInstance();