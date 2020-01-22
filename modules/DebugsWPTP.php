<?php
namespace wptelegrampro;

class DebugsWPTP extends WPTelegramPro
{
    public static $instance = null;
    protected $page_key, $page_title, $telegramFilterCountry = ['IR', 'CN', 'RU'], $url;

    public function __construct()
    {
        parent::__construct();
        $this->page_key = $this->plugin_key . '-debugs';
        $this->page_title = __('Debugs', $this->plugin_key);
        $this->url = get_bloginfo('url');
        add_action('admin_menu', array($this, 'menu'), 999999);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        add_filter('wptelegrampro_debugs_info', [$this, 'wptp_info'], 1);
        add_filter('wptelegrampro_debugs_info', [$this, 'php_info']);
        add_filter('wptelegrampro_debugs_info', [$this, 'wp_info']);
        add_filter('wptelegrampro_debugs_info', [$this, 'host_info']);
        add_filter('wptelegrampro_debugs_info', [$this, 'ssl_info']);
    }

    function admin_enqueue_scripts()
    {
        global $pagenow;
        if ($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'wp-telegram-pro-debugs') {
            wp_enqueue_style('thickbox');
            wp_enqueue_script('thickbox');
        }
    }

    /**
     * Add menu to WP Telegram Pro main menu
     */
    function menu()
    {
        add_submenu_page($this->plugin_key, $this->plugin_name . $this->page_title_divider . $this->page_title, $this->page_title, 'manage_options', $this->page_key, array($this, 'pageContent'));
    }

    /**
     * Add host info to debugs
     * @param $debugs string Debugs Info Array
     * @return string Debugs Info
     */
    function host_info($debugs)
    {
        // Host Info
        $domainInfo = new DomainInfoWPTP($this->url);
        $domainIP = $domainInfo->getIPAddress();
        if ($domainIP) {
            $domainCountry = $domainInfo->getLocation($domainIP);
            $countryCode = strtolower($domainCountry['countryCode']);
            $hostInfo = array(
                'IP' => $domainIP,
                __('Host Location', $this->plugin_key) => "<span class='ltr-right flex'><img src='https://www.countryflags.io/{$countryCode}/flat/16.png' alt='{$domainCountry['countryName']} Flag'> &nbsp;" . $domainCountry['countryCode'] . ' - ' . $domainCountry['countryName'] . '</span>'
            );
            if (in_array($domainCountry['countryCode'], $this->telegramFilterCountry))
                $hostInfo[__('Tip', $this->plugin_key)] = __('Your website host location on the list of countries that have filtered the telegram. For this reason, the plugin may not work well. My suggestion is to use a host of another countries.', $this->plugin_key);
            $debugs[__('Host', $this->plugin_key)] = $hostInfo;
        }
        return $debugs;
    }

    /**
     * Add SSL info to debugs
     * @param $debugs string Debugs Info Array
     * @return string Debugs Info
     */
    function ssl_info($debugs)
    {
        $ssl = is_ssl() ? $this->words['active'] : $this->words['inactive'];

        $debugs['SSL'] = array(
            __('Status', $this->plugin_key) => $ssl,
        );

        // SSL Info
        if (is_ssl()) {
            $ssl_info = array();
            $info = $this->checkSSLCertificate($this->url);

            if (is_array($info)) {
                $ssl_info[__('Issuer', $this->plugin_key)] = $info['issuer'];
                $ssl_info[__('Valid', $this->plugin_key)] = $info['isValid'] ? __('Yes', $this->plugin_key) : __('No', $this->plugin_key);

                $validFromDate = HelpersWPTP::localeDate($info['validFromDate']);
                $ssl_info[__('Valid from', $this->plugin_key)] = "<span class='ltr-right'>" . $info['validFromDate'] . ($info['validFromDate'] != $validFromDate ? " / {$validFromDate}" : '') . "</span>";

                $expirationDate = HelpersWPTP::localeDate($info['expirationDate']);
                $ssl_info[__('Valid until', $this->plugin_key)] = "<span class='ltr-right'>" . $info['expirationDate'] . ($info['expirationDate'] != $expirationDate ? " / {$expirationDate}" : '') . "</span>";

                $ssl_info[__('Is expired', $this->plugin_key)] = $info['isExpired'] ? __('Yes', $this->plugin_key) : __('No', $this->plugin_key);
                $ssl_info[__('Remaining days to expiration', $this->plugin_key)] = $info['daysUntilExpirationDate'];
                $ssl_info[__('Key', $this->plugin_key)] = $info['signatureAlgorithm'];
            } elseif (is_string($info))
                $ssl_info[__('SSL Info', $this->plugin_key)] = $info;

            $debugs['SSL'] = array_merge($debugs['SSL'], $ssl_info);
        } else {
            $debugs['SSL'][__('Tip', $this->plugin_key)] = $this->words['ssl_error'];
        }
        return $debugs;
    }

    /**
     * Add WordPress info to debugs
     * @param $debugs string Debugs Info Array
     * @return string Debugs Info
     */
    function wp_info($debugs)
    {
        global $wp_version;
        $debug = defined('WP_DEBUG') ? WP_DEBUG : false;
        $debugMode = $debug ? $this->words['active'] : $this->words['inactive'];
        $language = get_bloginfo('language');
        $charset = get_bloginfo('charset');
        $text_direction = is_rtl() ? 'RTL' : 'LTR';
        $debugs[__('WordPress')] = array(
            __('Version', $this->plugin_key) => $wp_version,
            __('Debugging Mode', $this->plugin_key) => $debugMode,
            __('Address', $this->plugin_key) => get_bloginfo('url'),
            __('Language', $this->plugin_key) => $language,
            __('Character encoding', $this->plugin_key) => $charset,
            __('Text Direction', $this->plugin_key) => $text_direction
        );
        if (version_compare($wp_version, '5.2', '>='))
            $debugs[__('WordPress')][__('Site Health', $this->plugin_key)] = '<a href="' . admin_url('site-health.php') . '">' . __('Check site health page', $this->plugin_key) . '</a>';
        return $debugs;
    }

    /**
     * Add PHP info to debugs
     * @param $debugs string Debugs Info Array
     * @return string Debugs Info
     */
    function php_info($debugs)
    {
        $phpversion = phpversion();
        $curl = function_exists('curl_version') ? curl_version()['version'] : $this->words['inactive'];
        $debugs['PHP'] = array(
            __('PHP Version', $this->plugin_key) => $phpversion,
            __('PHP CURL', $this->plugin_key) => $curl
        );
        return $debugs;
    }

    /**
     * Add WP Telegram Pro info to debugs
     * @param $debugs string Debugs Info Array
     * @return string Debugs Info
     */
    function wptp_info($debugs)
    {
        global $wpdb;
        $checkDBTable = $wpdb->get_var("show tables like '$this->db_users_table'") === $this->db_users_table;
        $checkDBTable = $checkDBTable ? $this->words['yes'] : $this->words['no'];
        $debugs[$this->plugin_name] = array(
            __('Plugin Version', $this->plugin_key) => WPTELEGRAMPRO_VERSION,
            __('Plugin DB Table Created', $this->plugin_key) => $checkDBTable
        );

        if ($updateInfo = $this->check_update_plugin())
            $debugs[$this->plugin_name][__('Update', $this->plugin_key)] = '<a href="' . $updateInfo['updateDetailURL'] . '" class="thickbox open-plugin-details-modal">' . __('Update to the new version', $this->plugin_key) . ' ' . $updateInfo['newVersion'] . '</a>';

        return $debugs;
    }

    protected function check_update_plugin($file = null)
    {
        if ($file == null) $file = 'wp-telegram-pro/WPTelegramPro.php';
        $plugin_updates = get_plugin_updates();
        if (in_array($file, array_keys($plugin_updates)))
            return array(
                'name' => $plugin_updates[$file]->Name,
                'newVersion' => $plugin_updates[$file]->update->new_version,
                'updateURL' => wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=') . $file, 'upgrade-plugin_' . $file),
                'updateDetailURL' => self_admin_url('plugin-install.php?tab=plugin-information&plugin=' . $plugin_updates[$file]->update->slug . '&section=changelog&TB_iframe=true&width=772&height=367')
            );
        return false;
    }

    /**
     * Debugs page content
     */
    function pageContent()
    {
        $debugs = apply_filters('wptelegrampro_debugs_info', []);
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
     * @param $host string URL
     * @return boolean|array
     */
    function checkSSLCertificate($host)
    {
        if (!is_ssl() || !class_exists('wptelegrampro\SSLCertificateWPTP')) return false;
        try {
            $SSLCertificate = new SSLCertificateWPTP($host);
            return $SSLCertificate->request()->response();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
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