<?php

namespace wptelegrampro;
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
    public static function dd($data, $echo = true)
    {
        if (!$echo)
            ob_start();
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
        if (!$echo)
            return ob_get_clean();
    }

    public static function forms_select($field_name, $items = array(), $args = array())
    {
        $defaults = array(
            'blank' => false,
            'field_id' => false,
            'onchange' => false,
            'exclude' => false,
            'multiple' => false,
            'selected' => 0,
            'echo' => true,
            'class' => ''
        );
        $args = wp_parse_args($args, $defaults);

        if (!$args['field_id'])
            $args['field_id'] = str_replace('[]', '', $field_name);

        $add_html = array();
        self::add_html_attr($args['onchange'], 'onchange', $add_html);
        self::add_html_attr($args['class'], 'class', $add_html);
        self::add_html_attr($args['multiple'], 'multiple', $add_html);

        ob_start();
        ?>
        <select name="<?php echo esc_attr($field_name); ?>"
                id="<?php echo esc_attr($args['field_id']); ?>"
            <?php echo wp_strip_all_tags(implode(' ', $add_html)); // WPCS: XSS ok.
            ?>>
            <?php if ($args['blank']) { ?>
                <option value="" <?php echo($args['selected'] == '' || is_array($args['selected']) && count($args['selected']) === 0 || is_array($args['selected']) && isset($args['selected'][0]) && $args['selected'][0] == '' ? 'selected' : '') ?>><?php echo ($args['blank'] == 1) ? ' ' : '- ' . esc_attr($args['blank']) . ' -'; ?></option>
            <?php } ?>
            <?php foreach ($items as $item_id => $item_title) {
                if ($args['exclude'] && (is_array($args['exclude']) && in_array($item_id, $args['exclude']) || $args['exclude'] == $item_id))
                    continue;

                $selected = false;
                if ($args['selected']) {
                    if (is_array($args['selected']))
                        $selected = in_array($item_id, $args['selected']);
                    else
                        $selected = $item_id == $args['selected'];
                }
                ?>
                <option value="<?php echo esc_attr($item_id); ?>" <?php selected($selected, true); ?>>
                    <?php echo esc_html($item_title) ?>
                </option>
            <?php } ?>
        </select>
        <?php

        $select = ob_get_clean();

        if ($args['echo'])
            echo $select;
        else
            return $select;
    }

    /**
     * FrmFormsHelper::add_html_attr
     * @param string $class
     * @param string $param
     * @param array $add_html
     *
     * @since 2.0.6
     */
    public static function add_html_attr($class, $param, &$add_html)
    {
        if (!empty($class)) {
            $add_html[$param] = sanitize_title($param) . '="' . esc_attr(trim(sanitize_text_field($class))) . '"';
        }
    }

    /**
     * Convert html > ul > li to a PHP array
     * https://gist.github.com/molotovbliss/18acc1522d3c23382757df2dbe6f0134
     * @param string $ul ul>li HTML tags
     * @return array|bool
     */
    public static function ul_to_array($ul)
    {
        if (is_string($ul)) {
            // encode ampersand appropiately to avoid parsing warnings
            $ul = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $ul);
            if (!$ul = simplexml_load_string($ul)) {
                trigger_error("Syntax error in UL/LI structure");
                return false;
            }
            return self::ul_to_array($ul);
        } else if (is_object($ul)) {
            $output = array();
            foreach ($ul->li as $li) {
                $output[] = (isset($li->ul)) ? self::ul_to_array($li->ul) : (string)$li;
            }
            return $output;
        } else return false;
    }

    public static function getCurrentURL()
    {
        $pageURL = 'http';
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
            $pageURL .= "s";
        }
        $pageURL .= "://";
        $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        return $pageURL;
    }

    public static function getURLHost($url)
    {
        if (!HelpersWPTP::startsWith($url, ['http://', 'https://', 'ssl://'])) {
            $url = "https://{$url}";
        }

        if (strlen($url) < 61 && function_exists('idn_to_ascii') && defined('INTL_IDNA_VARIANT_UTS46')) {
            $url = idn_to_ascii($url, false, INTL_IDNA_VARIANT_UTS46);
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception("String `{$url}` is not a valid url.");
        }

        $parsedUrl = parse_url($url);

        if (!isset($parsedUrl['host'])) {
            throw new \Exception("Could not determine host from url `{$url}`.");
        }

        return $parsedUrl['host'];
    }

    /**
     * Remove Unused Shortcodes
     * https://www.maketecheasier.com/remove-unused-shortcode-from-posts-wordpress/
     * @param string $content | String to strip shortcodes
     * @return string | String with strip shortcodes
     */
    public static function stripShortCodes($content)
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


    public static function startsWith($haystack, $needles)
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
    public static function endsWith(string $haystack, $needles)
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
    public static function substr(string $string, int $start, int $length = null)
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
    public static function length(string $value)
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
    public static function strContains(string $haystack, $needles)
    {
        foreach ((array)$needles as $needle) {
            if ($needle != '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function localeDate($time = null, $format = "Y/m/d H:i:s")
    {
        $format = apply_filters('wptelegrampro_date_format', $format);

        if ($time == null) $time = date("Y-m-d H:i:s");

        if (function_exists('parsidate'))
            return parsidate($format, $time, 'per');

        if (function_exists('jdate'))
            return jdate($format, $time, false, true);

        if (!self::isValidTimeStamp($time))
            $time = strtotime($time);

        if (function_exists('wp_date'))
            return wp_date($format, $time);
        else
            return date($format, $time);
    }

    public static function isValidTimeStamp($strTimestamp)
    {
        return ((string)(int)$strTimestamp === $strTimestamp)
            && ($strTimestamp <= PHP_INT_MAX)
            && ($strTimestamp >= ~PHP_INT_MAX);
    }

    public static function getUserIP()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return apply_filters('wpb_get_ip', $ip);
    }

    public static function wordsCamelCaseToSentence($words)
    {
        $words = preg_split('~[^A-Z]+K|(?=[A-Z][^A-Z]+)~', $words, 0, PREG_SPLIT_NO_EMPTY);
        return ucwords(implode(' ', $words));
    }

    public static function secondsToHumanTime($seconds, $labels = [], $separator = ', ')
    {
        $seconds = intval($seconds);
        $times = $times_ = [];
        $times['days'] = floor($seconds / (3600 * 24));
        $times['hours'] = floor($seconds / 3600) % 24;
        $times['minutes'] = floor(($seconds / 60) % 60);
        $times['seconds'] = $seconds % 60;

        foreach ($times as $key => $value) {
            if ($value > 0)
                $times_[] = $value . ' ' . (isset($labels[$key]) ? $labels[$key] : ucwords($key));
        }
        $output = implode($separator, $times_);
        return $output;
    }

    public static function removeInnerTextTag($string, $tags = [])
    {
        foreach ($tags as $tag)
            $string = preg_replace('#(<' . $tag . '.*?>).*?(</' . $tag . '>)#', '$1$2', $string);

        return $string;
    }

    public static function br2nl($string, $removeMultiple = false)
    {
        $string = preg_replace('#<br\s*/?>#i', "\n", $string);
        if ($removeMultiple) {
            //$string = preg_replace('/\n\r+/', "\n", $string);
            //$string = preg_replace("/[" . chr(10) . "]+/", "\n", $string);
            //$string = preg_replace("/[\r\n]+/", "\n", $string);
            $string = preg_replace('/[ \t]+/', ' ', preg_replace('/\s*$^\s*/m', "\n", $string));
            //$string = preg_replace('/\s+/', ' ', $string);
            //$string = preg_replace('/([\s])\1+/', ' ', $string);
        }
        return $string;
    }

    /*
     * https://stackoverflow.com/a/25584726/3224296
     * */
    public static function string2Stars($string = '', $first = 0, $last = 0, $rep = '*')
    {
        if ($first == 0 && $last == 0) {
            $third = intval(mb_strlen($string) / 3);
            $first = $third;
            $last = $third * -1;
        }
        $begin = substr($string, 0, $first);
        $middle = str_repeat($rep, strlen(substr($string, $first, $last)));
        $end = substr($string, $last);
        $stars = $begin . $middle . $end;
        return $stars;
    }

    public static function randomStrings($length, $string_type = array('NUMBER'))
    {
        $original_string = array();
        if (in_array("NUMBER", $string_type)) {
            $original_string = array_merge(range(1, 9), $original_string);
        }
        if (in_array("CLCASE", $string_type)) {
            $original_string = array_merge(range('a', 'z'), $original_string);
        }
        if (in_array("CUCASE", $string_type)) {
            $original_string = array_merge(range('A', 'Z'), $original_string);
        }

        $original_string = implode("", $original_string);
        $original_string = strlen($original_string) < $length ? str_repeat($original_string, intval($length / strlen($original_string) + 1)) : $original_string;
        return substr(str_shuffle($original_string), 0, $length);
    }
}
