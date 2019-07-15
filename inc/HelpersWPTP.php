<?php

use function Spatie\SslCertificate\length;
use function Spatie\SslCertificate\substr;

/**
 * WPTelegramPro Helper functions
 *
 * @link       https://wordpress.org/plugins/wp-telegram-pro
 * @since      1.0.0
 *
 * @package    WPTelegramPro
 * @subpackage WPTelegramPro/inc
 */
class HelpersWPTP
{

    /**
     * Remove Unused Shortcodes
     * https://www.maketecheasier.com/remove-unused-shortcode-from-posts-wordpress/
     * @param string $content | String to strip shortcodes
     * @return string | String with strip shortcodes
     */
    public static function strip_shortcodes($content)
    {
        $pattern = self::get_unused_shortcode_regex();
        $content = preg_replace_callback('/' . $pattern . '/s', 'strip_shortcode_tag', $content);
        return $content;
    }

    private static function get_unused_shortcode_regex()
    {
        global $shortcode_tags;
        $tagnames = array_keys($shortcode_tags);
        $tagregexp = join('|', array_map('preg_quote', $tagnames));
        $regex = '\\[(\\[?)';
        $regex .= "(?!$tagregexp)";
        $regex .= '\\b([^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*)*?)(?:(\\/)\\]|\\](?:([^\\[]*+(?:\\[(?!\\/\\2\\])[^\\[]*+)*+)\\[\\/\\2\\])?)(\\]?)';
        return $regex;
    }

    /**
     * Scan the api path, recursively including all PHP files
     * https://gist.github.com/pwenzel/3438784
     * @param string $dir
     * @param int $depth (optional)
     */
    public static function requireAll($dir, $depth = 0)
    {
        if ($depth > 5) return;
        $scan = glob($dir . DIRECTORY_SEPARATOR . "*");
        foreach ($scan as $path) {
            if (preg_match('/\.php$/', $path)) {
                require_once $path;
            } elseif (is_dir($path)) {
                self::requireAll($path, $depth + 1);
            }
        }
    }

    /**
     * Recursive copy files
     * https://gist.github.com/gserrano/4c9648ec9eb293b9377b
     * @param $src string Source Directory
     * @param $dst string Destination Directory
     */
    public static function recursiveCopy($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . DIRECTORY_SEPARATOR . $file)) {
                    self::recursiveCopy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                } else {
                    copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                }
            }
        }
        closedir($dir);
    }


    public static function startsWith($haystack, $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle != '' && mb_strpos($haystack, $needle) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string ends with a given substring.
     *
     * @param string $haystack
     * @param string|array $needles
     *
     * @return bool
     */
    public static function endsWith(string $haystack, $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ((string)$needle === self::substr($haystack, -self::length($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the portion of string specified by the start and length parameters.
     *
     * @param string $string
     * @param int $start
     * @param int|null $length
     *
     * @return string
     */
    public static function substr(string $string, int $start, int $length = null): string
    {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    /**
     * Return the length of the given string.
     *
     * @param string $value
     *
     * @return int
     */
    public static function length(string $value): int
    {
        return mb_strlen($value);
    }

    /**
     * Determine if a given string contains a given substring.
     *
     * @param string $haystack
     * @param string|array $needles
     *
     * @return bool
     */
    public static function strContains(string $haystack, $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle != '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function localeDate($time, $format = "Y/m/d H:i:s")
    {
        if (function_exists('parsidate'))
            return parsidate($format, $time, 'en');

        if (function_exists('jdate'))
            return jdate($format, $time, false, false);

        return $time;
    }
}