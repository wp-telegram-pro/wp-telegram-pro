## WP Telegram Pro [[Download Stable Version](https://wordpress.org/plugins/wp-telegram-pro)]

### I stopped developing this plugin, please use a new version of the plugin called [Teligro](https://github.com/teligro/teligro).

[![MIT Licence](https://badges.frapsoft.com/os/mit/mit.svg)](https://opensource.org/licenses/mit-license.php)   

Description
-----------

**Integrate your WordPress site with Telegram**
* New comments, Recovery mode, Auto core update, Users login, Register a new user notification
* Search in WordPress post types
* Send post types manually or automatically to Telegram channel
* Display Telegram channel members count with shortcode
* Connect WordPress profile to Telegram account
* Two Step Telegram bot Authentication
* Connect to Telegram with Proxy

**Integrate with E-Commerce plugins:**
* [WooCommerce](https://wordpress.org/plugins/woocommerce) – Sale products on the Telegram bot. Send product to Telegram channels. New order, Order status change, Product low/no stock, new order note notification

**Integrate with Forms plugins:**
* [Contact Form 7](https://wordpress.org/plugins/contact-form-7) and [Flamingo](https://wordpress.org/plugins/flamingo)
* [WPForms](https://wpforms.com) and [Contact Form by WPForms](https://wordpress.org/plugins/wpforms-lite)
* [Formidable Form Builder](https://wordpress.org/plugins/formidable)
* [Gravity Forms](https://www.gravityforms.com)
* [Ninja Forms](https://wordpress.org/plugins/ninja-forms)
* [Caldera Forms](https://wordpress.org/plugins/caldera-forms)
* [Everest Forms](https://wordpress.org/plugins/everest-forms)
* [HappyForms](https://wordpress.org/plugins/happyforms)
* [weForms](https://wordpress.org/plugins/weforms)
* [Visual Form Builder](https://wordpress.org/plugins/visual-form-builder)
* [Quform](https://www.quform.com)
* [HTML Forms](https://wordpress.org/plugins/html-forms)
* [Forminator](https://wordpress.org/plugins/forminator)

**Integrate with Newsletter plugins:**
* [Newsletter](https://wordpress.org/plugins/newsletter)
* [MC4WP: Mailchimp for WordPress](https://wordpress.org/plugins/mailchimp-for-wp)

**Integrate with Security plugins:**
* [Wordfence Security](https://wordpress.org/plugins/wordfence)
* [iThemes Security (formerly Better WP Security)](https://wordpress.org/plugins/better-wp-security)
* [All In One WP Security & Firewall](https://wordpress.org/plugins/all-in-one-wp-security-and-firewall)
* [Cerber Security, Antispam & Malware Scan](https://wordpress.org/plugins/wp-cerber)
* [DoLogin Security](https://wordpress.org/plugins/dologin)

**Integrate with Backup plugins:**
* [BackWPup – WordPress Backup Plugin](https://wordpress.org/plugins/backwpup)
* [BackUpWordPress](https://wordpress.org/plugins/backupwordpress)

**Integrate with other plugins:**
* [WP SMS](https://wordpress.org/plugins/wp-sms)
* [WP Statistics](https://wordpress.org/plugins/wp-statistics)
* [WP User Avatar](https://wordpress.org/plugins/wp-user-avatar)

-----------

Development Source:
* [Telegram Bot API](https://core.telegram.org/bots/api)
* [WordPress Developer](http://developer.wordpress.org) 

Useful Tools:
* [Json Parser](http://json.parser.online.fr/)


Changelog
-----------
**2.1 2020-04-12**
* Now non-administrator user can receive plugins notification
* Add Tunnel Proxy
* Control the display of posts buttons
* Fixed some bugs

**2.0.3 2020-01-26**
* Fixed plugin activate error

**2.0.2 2020-01-23**
* Fixed error in the addons

**2.0.1 2020-01-22**
* Fixed tags with space
* Fixed create plugin DB table
* Fixed duplicate plugin menu 

**2.0 2020-01-19**
* Two Step Telegram bot Authentication
* Integrate with DoLogin Security plugin
* Fixed error in PHP5.*

**1.9**
* Integrate with some plugins
* New WordPress and WooCommerce notification option
* Fix WooCommerce "Exclude Categories" and "Exclude Display Categories" option
* Fix the problem of encoding the text sent to the telegram

**1.8**
* Add quick send to channel
* Connect WordPress profile to Telegram account
* WooCommerce new order and order status change notification
* Fixed comment notification
* Fixed problem displaying list of pattern tags.
* Fixed warning with PHP V7.2.*

**1.7.2**
* Fixed some bugs in PHP5.*

**1.7.1**
* Add host info (IP/Location) to debugs page
* Fixed some bugs

**V 1.7**
* Add debugs page
* Add helps page
* Cleans post excerpt from tags and unused shortcodes

**V 1.6**
* New option for display channels metabox base on user role
* Add currency symbol to channel pattern tags, `{currency-symbol}`
* Fixed some bugs

**V 1.5**
* Inline button for channel message
* Fixed some bugs

**V 1.4**
* Delay time to send channels
* Fixed some bugs

**V 1.3**
* Add proxy settings

**V 1.2**
* Compatible with RTL languages
* Add custom field and terms to pattern tags: `{cf:price}`, `{terms:taxonomy}`
* Add if statement to pattern tags: `{if='cf:price'}Price: {cf:price}{/if}`
* Admin can force update telegram keyboard

**V 1.1**
* Display Telegram channel members count with shortcode: `[channel_members_wptp channel="channel username" formatting="1"]`

**V 1.0**
* Receive command from Telegram
* Settings Page
* WooCommerce product list (with pagination)
* Product Details: Price, Weight, Dimensions, Attributes, Rating, Category, Variables.
* Product Image Gallery
* Customer can add product to cart
* Display product variable and user can select
* Posts archive
* Category list
* Search in post type
* Comment notification
* Send post types to channel
* Gutenberg editor compatible

Develop Plan
-----------
-

Warning
-----------
The plugin is in development, Not recommended in the product. You can download stable version in [WordPress.org plugins repository](https://wordpress.org/plugins/wp-telegram-pro)