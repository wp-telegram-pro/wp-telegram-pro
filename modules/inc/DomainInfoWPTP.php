<?php

namespace wptelegrampro;

/**
 * WPTelegramPro Domain Info
 *
 * @link       https://wordpress.org/plugins/wp-telegram-pro
 * @since      1.0.0
 *
 * @package    WPTelegramPro
 * @subpackage WPTelegramPro/modules/inc
 */
class DomainInfoWPTP
{
    protected $host;

    public function __construct($url)
    {
        $this->host = HelpersWPTP::getURLHost($url);
        return $this;
    }

    function getLocation($ip)
    {
        // Download DB File: https://lite.ip2location.com/file-download, https://lite.ip2location.com/download?id=2
        $db = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'IP2LOCATION-LITE-DB1.BIN';
        $db = new \IP2Location\Database($db, \IP2Location\Database::FILE_IO);
        $records = $db->lookup($ip, \IP2Location\Database::COUNTRY);

        return $records;
    }

    function getIPAddress()
    {
        $domain = 'www.' . $this->host;
        $ip = gethostbyname($domain);
        if ($ip !== $domain)
            return $ip;

        $res = $this->getAddresses($this->host);
        if (count($res) == 0) {
            $res = $this->getAddresses($domain);
        }

        if (isset($res['ip']))
            return $res['ip'];
        elseif (isset($res['ipv6']))
            return $res['ipv6'];

        return false;
    }

    function getAddresses($domain)
    {
        $records = dns_get_record($domain);
        $res = array();
        foreach ($records as $r) {
            if ($r['host'] != $domain) continue; // glue entry
            if (!isset($r['type'])) continue; // DNSSec

            if ($r['type'] == 'A') $res['ip'] = $r['ip'];
            if ($r['type'] == 'AAAA') $res['ipv6'] = $r['ipv6'];
        }
        return $res;
    }
}