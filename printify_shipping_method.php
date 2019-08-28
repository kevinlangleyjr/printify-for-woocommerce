<?php
class PrintifyShipping extends WC_Shipping_Method {

    private $printify_package = false;
    public $override_defaults = true;
    private $_rates = [];

    public function __construct()
    {
        $this->api = new Printify_Api;
        $this->id = 'printify_shipping';
        $this->method_title = $this->title = 'Printify Shipping';
        $this->method_description = 'Calculate live shipping rates based on actual Printify shipping costs.';

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_shipping_' . $this->id, array(&$this, 'process_admin_options'));

        $this->enabled = $this->get_option('enabled');
        // $this->show_warnings = $this->get_option('show_warnings') == 'yes';
        $this->override_defaults = $this->get_option('override_defaults') == 'yes';

        // Initialize shipping methods for specific package (or no package)
        add_filter('woocommerce_load_shipping_methods', array($this, 'woocommerce_load_shipping_methods'), 10000);

        add_filter('woocommerce_shipping_methods', array($this, 'woocommerce_shipping_methods'), 10000);

        add_filter('woocommerce_cart_shipping_packages', array($this, 'woocommerce_cart_shipping_packages'), 10000);
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'         => __( 'Enable/Disable', 'woocommerce' ),
                'type'          => 'checkbox',
                'label'         => __( 'Enable this shipping method', 'woocommerce' ),
                'default'       => 'no'
            ),
            'override_defaults' => array(
                'title'         => 'Disable Woocommerce rates',
                'type'          => 'checkbox',
                'label'         => 'Disable standard Woocommerce rates for products fulfilled by Printify',
                'default'       => 'yes'
            )
        );
    }

    public function woocommerce_load_shipping_methods($package = array()) {
        $this->printify_package = false;

        // error_log(json_encode($package));

        if ($package) {
             if($this->enabled == 'yes' && isset($package['printify'])) {
                 $this->printify_package = true;
                 if($this->override_defaults) {
                    
                    // Unregister all shipping methods except free shipping.
					foreach( WC()->shipping()->shipping_methods as $shipping_method_key => $shipping_method ) {
						if ( get_class( $shipping_method ) !== 'WC_Shipping_Free_Shipping' ) {
							unset( WC()->shipping()->shipping_methods[ $shipping_method_key ] );
						}
					}
                 }
                 WC()->shipping()->register_shipping_method($this);
             }
        } else if( ! $package) {
            WC()->shipping()->register_shipping_method($this);
        }
    }

    public function woocommerce_shipping_methods($methods) {
        if($this->override_defaults && $this->printify_package && version_compare(WC()->version, '2.6', '<')) {
            return array();
        }

        return $methods;
    }

    public function woocommerce_cart_shipping_packages($packages = array()) {
        if ($this->enabled == 'no'){
            return $packages;
        }

        $items = [];

        foreach ($packages as $p) {
            foreach ($p['contents'] as $item) {
                $items[]= [
                    'quantity' => $item['quantity'],
                    'sku'      => $item['data']->get_sku()
                ];
            }
        }

        $data = [
            'items'   => $items,
            'country' => $p['destination']['country'],
            'state'   => $p['destination']['state'],
        ];

        $this->_rates = $this->api->post('callbacks/woo/shipping', [], $data);


        if ($this->_rates && ! empty($this->_rates['skus'])) {
            $_packages = [];

            $new_contents = array(
                'printify' => array(),
                'woocommerce' => array()
            );

            foreach ($packages as $p) {
                foreach ($p['contents'] as $item) {
                    if (in_array($item['data']->get_sku(), $this->_rates['skus'])) {
                        $new_contents['printify'][]= $item;
                    } else {
                        $new_contents['woocommerce'][]= $item;
                    }
                }
            }

            foreach ($packages as $package) {
                foreach ($new_contents as $key => $contents) {
                    if ($contents) {
                        $new_package = $package;
                        $new_package['contents_cost'] = 0;
                        $new_package['contents'] = $contents;

                        foreach ($contents as $item ) {
                            if ( $item['data']->needs_shipping() ) {
                                if ( isset( $item['line_total'] ) ) {
                                    $new_package['contents_cost'] += $item['line_total'];
                                }
                            }
                        }
                        if ($key == 'printify') {
                            $new_package['printify'] = true;
                        }
                        $_packages[]= $new_package;
                    }
                }
            }

            return $_packages;
        } else {
            return $packages;
        }

    }

    public function calculate_shipping($package = array())
    {
        // error_log(json_encode($this->_rates));
        if (isset($this->_rates['shipping_standart']) && isset($this->_rates['shipping_standart']['cost'])) {
            $rateData = array(
                'id'       => $this->id . '_s',
                'label'    => 'Standard',
                'cost'     => $this->_rates['shipping_standart']['cost'] / 100,
                'calc_tax' => 'per_order'
            );

            $this->add_rate($rateData);
        }

        if (isset($this->_rates['shipping_express']) && isset($this->_rates['shipping_express']['cost'])) {
            $rateData = array(
                'id'       => $this->id . '_e',
                'label'    => 'Express',
                'cost'     => $this->_rates['shipping_express']['cost'] / 100,
                'calc_tax' => 'per_order'
            );

            $this->add_rate($rateData);
        }
    }

}
