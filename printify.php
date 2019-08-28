<?php
/*
Plugin Name: Printify
Version: 1.3
Description: Embeded Printify app
*/
if ( ! defined('ABSPATH')) exit;

require_once('printify_api.php');

class PrintifyPlugin {

    const HOST = 'https://printify.com/app/';

    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init' ]);
    }

    public function init()
    {
        $this->add_embeded_app();
        $this->add_embeded_app_css();

        if (class_exists('WC_Shipping_Method')) {
            $this->init_woocommerce();
        }
    }

    public function init_woocommerce()
    {
        require_once 'printify_shipping_method.php';
        $this->shipping = new PrintifyShipping;
    }

    public function add_embeded_app_css()
    {
        add_action('admin_head', [$this, 'render_embeded_app_css']);
    }

    public function add_embeded_app()
    {
        add_action('admin_menu', [$this, 'add_embeded_app_page']);
    }

    public function add_embeded_app_page()
    {
        add_menu_page(
            'Printify',
            'Printify',
            'manage_options',
            'printify',
            [$this, 'render_embeded_app'],
            plugin_dir_url(__FILE__) . 'images/printify_logo.png',
            55
        );
    }

    public function render_embeded_app()
    {
        ?>
        <iframe
            src="<?php echo self::HOST; ?>?woo=<?php echo urlencode(get_home_url()) ;?>&label=<?php echo urlencode(bloginfo('name')) ;?>" id="printify"></iframe>
        <?php
    }

    public function render_embeded_app_css()
    {
        $hideUpdateNag = null;
        if (isset($_GET['page']) && $_GET['page'] == 'printify') {
            $hideUpdateNag = '.update-nag, #message, .notice, .updated, #wpfooter { display: none !important; }';
        }
        echo '<style>
            #toplevel_page_printify img {
                padding: 0px !important;
                opacity: 1 !important;
            }
            #printify {
                width: calc(100% + 20px);
                height: calc(100vh - 32px);
                margin-bottom: -100px;
                position: relative;
                z-index: 1;
                margin-left: -20px;
            }
            ' . $hideUpdateNag . '
        </style>';
    }

}

new PrintifyPlugin;