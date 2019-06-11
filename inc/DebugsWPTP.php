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
        add_submenu_page($this->plugin_key, $this->plugin_name . ' - ' . $this->page_title, $this->page_title, 'manage_options', $this->page_key, array($this, 'pageContent'));
    }
    
    function pageContent()
    {
        echo 'Debugs';
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