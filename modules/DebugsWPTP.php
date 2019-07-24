<?php

class DebugsWPTP extends WPTelegramPro
{
    public static $instance = null;
    protected $page_key, $page_title, $telegramFilterCountry = ['IR'];

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
        $url = get_bloginfo('url');
        $phpversion = phpversion();
        $curl = function_exists('curl_version') ? curl_version()['version'] : $this->words['inactive'];

        $debug = defined('WP_DEBUG') ? WP_DEBUG : false;
        $debugMode = $debug ? $this->words['active'] : $this->words['inactive'];
        $ssl = is_ssl() ? $this->words['active'] : $this->words['inactive'];
        $language = get_bloginfo('language');
        $charset = get_bloginfo('charset');
        $text_direction = is_rtl() ? 'RTL' : 'LTR';
        $checkDBTable = $wpdb->get_var("show tables like '$this->db_table'") === $this->db_table;
        $checkDBTable = $checkDBTable ? $this->words['yes'] : $this->words['no'];

        $debugs = array(
            $this->plugin_name => array(
                __('Plugin Version', $this->plugin_key) => WPTELEGRAMPRO_VERSION,
                __('Plugin DB Table Created', $this->plugin_key) => $checkDBTable
            ),
            'PHP' => array(
                __('PHP Version', $this->plugin_key) => $phpversion,
                __('PHP CURL', $this->plugin_key) => $curl
            ),
            __('WordPress') => array(
                __('Version', $this->plugin_key) => $wp_version,
                __('Debugging Mode', $this->plugin_key) => $debugMode,
                __('Address', $this->plugin_key) => get_bloginfo('url'),
                __('Language', $this->plugin_key) => $language,
                __('Character encoding', $this->plugin_key) => $charset,
                __('Text Direction', $this->plugin_key) => $text_direction
            ),
            'SSL' => array(
                __('Status', $this->plugin_key) => $ssl,
            )
        );

        if (is_ssl()) {
            $ssl_info = array();
            $info = $this->checkSSLCertificate($url);
            if (is_array($info)) {
                $ssl_info[__('Issuer', $this->plugin_key)] = $info['issuer'];
                $ssl_info[__('Valid', $this->plugin_key)] = $info['isValid'] ? __('Yes', $this->plugin_key) : __('No', $this->plugin_key);
                $ssl_info[__('Valid from', $this->plugin_key)] = HelpersWPTP::localeDate($info['validFromDate']);
                $ssl_info[__('Valid until', $this->plugin_key)] = HelpersWPTP::localeDate($info['expirationDate']);
                $ssl_info[__('Is expired', $this->plugin_key)] = $info['isExpired'] ? __('Yes', $this->plugin_key) : __('No', $this->plugin_key);
                $ssl_info[__('Remaining days to expiration', $this->plugin_key)] = $info['daysUntilExpirationDate'];
                $ssl_info[__('Key', $this->plugin_key)] = $info['signatureAlgorithm'];
            } elseif (is_string($info))
                $ssl_info[__('SSL Info', $this->plugin_key)] = $info;

            $debugs['SSL'] = array_merge($debugs['SSL'], $ssl_info);
        }

        $domainInfo = new DomainInfoWPTP($url);
        $domainIP = $domainInfo->getIPAddress();
        $domainCountry = $domainInfo->getLocation($domainIP);
        $countryCode = strtolower($domainCountry['countryCode']);
        $hostInfo = array(
            'IP' => $domainIP,
            __('Host Location', $this->plugin_key) => "<span class='ltr-right flex'><img src='https://www.countryflags.io/{$countryCode}/flat/16.png' alt='{$domainCountry['countryCode']} Flag'> &nbsp;" . $domainCountry['countryCode'] . ' - ' . $domainCountry['countryName'] . '</span>'
        );
        if (in_array($domainCountry['countryCode'], $this->telegramFilterCountry))
            $hostInfo[__('Tip', $this->plugin_key)] = __('Your website host location on the list of countries that have filtered the telegram. For this reason, the plugin may not work well. My suggestion is to use a host of other countries.', $this->plugin_key);
        $debugs[__('Host', $this->plugin_key)] = $hostInfo;
        ?>
        <div class="wrap wptp-wrap">
            <h1 class="wp-heading-inline"><?php echo $this->plugin_name . $this->page_title_divider . $this->page_title ?></h1>
            <table class="table table-light table-th-bold table-bordered">
                <tbody>
                <?php
                foreach ($debugs as $key => $debug) {
                    echo '<tr><th colspan="2">' . $key . '</th></tr>';
                    foreach ($debug as $title => $value) {
                        echo '<tr><td>' . $title . '</td><td>' . $value . '</td></tr>';
                    }
                }
                ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Check SSL Certificate
     *
     * @return boolean|array
     */
    function checkSSLCertificate($host)
    {
        if (!is_ssl() || !class_exists('SSLCertificateWPTP')) return false;
        try {
            $SSLCertificate = new SSLCertificateWPTP($host);
            return $SSLCertificate->request()->response();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    function checkHostCountry($domain)
    {

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