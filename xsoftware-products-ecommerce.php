<?php
/*
Plugin Name: XSoftware Products E-Commerce
Description: Products E-Commerce management on wordpress.
Version: 1.0
Author: Luca Gasperini
Author URI: https://xsoftware.it/
Text Domain: xsoftware_products
*/

if(!defined("ABSPATH")) die;

if (!class_exists("xs_products_ecommerce_plugin")) :

class xs_products_ecommerce_plugin
{

        private $options = array( );


        public function __construct()
        {
                add_action('init', [$this, 'override_filter']);
        }

        function override_filter()
        {
                $cart_option = get_option('xs_options_cart');
                $this->checkout = $cart_option['sys']['checkout'];

                global $xs_products_plugin;
                global $xs_cart_plugin;

                remove_filter('xs_cart_sale_order_html', [$xs_cart_plugin, 'show_cart_html']);
                remove_filter('xs_cart_add_html', [$xs_cart_plugin,'cart_add_html']);
                remove_filter('xs_cart_approved_html', [$xs_cart_plugin,'show_cart_approved_html']);
                remove_filter('xs_cart_empty_html', [$xs_cart_plugin, 'show_cart_empty_html']);
                remove_filter('xs_product_archive_html', [$xs_products_plugin, 'archive_html'], 0);
                remove_filter('xs_product_single_html', [$xs_products_plugin, 'single_html'], 0);
                add_filter('xs_cart_sale_order_html', [$this, 'show_cart_html']);
                add_filter('xs_cart_add_html', [$this,'cart_add_html']);
                add_filter('xs_cart_approved_html', [$this,'show_cart_approved_html']);
                add_filter('xs_cart_empty_html', [$this, 'show_cart_empty_html']);
                add_filter('xs_product_archive_html', [ $this, 'archive_html' ], 0, 2);
                add_filter('xs_product_single_html', [ $this, 'single_html' ], 0, 2);
        }

        function cart_add_html($post_id)
        {
                $output = '';

                wp_enqueue_style(
                        'xs_product_template',
                        plugins_url('style/single.min.css',__FILE__)
                );

                $btn = xs_framework::create_button([
                        'name' => 'add_cart',
                        'value' => $post_id,
                        'text' => 'Aggiungi al Carrello'
                ]);
                $qt_label = '<span>Mesi:</span>';
                $qt = xs_framework::create_input_number([
                        'name' => 'qt',
                        'value' => 1,
                        'min' => 1,
                        'max' => 9999999
                ]);

                $qt_container = xs_framework::create_container([
                        'class' => 'qt',
                        'obj' => [$qt_label, $qt],
                        'echo' => FALSE
                ]);

                $output .= '<form action="'.$this->checkout.'" method="get">';
                $output .= xs_framework::create_container([
                        'class' => 'xs_add_cart_container',
                        'obj' => [$qt_container, $btn],
                        'echo' => FALSE
                ]);
                $output .= '</form>';

                return $output;
        }

        function single_html($id, $single)
        {
                wp_enqueue_style(
                        'xs_product_template',
                        plugins_url('style/single.min.css', __FILE__)
                );
                $image = get_the_post_thumbnail_url( $id, 'medium' );
                $title = get_the_title($id);
                $price = apply_filters('xs_cart_item_price', $id);

                echo '<div class="product_item">';
                echo '<div class="product_content">';
                echo '<img src="'.$image.'"/>';
                echo '<div class="info">';
                echo '<h1>'.$title.'</h1>';
                echo '<p class="descr">'.$single['descr'].'</p>';
                echo '<p class="text">'.$single['text'].'</p>';
                echo '</div>';
                if(!empty($price)) {
                        echo '<div class="cart">';
                        echo '<span>Prezzo:</span>';
                        echo '<i>'.$price['price'].' '.$price['currency_symbol'].'</i>';
                        echo apply_filters('xs_cart_add_html', $id);
                        echo '</div>';
                }
                echo '</div>';
                echo '</div>';
        }

        function archive_html($archive, $user_lang)
        {
                $output = '';
                wp_enqueue_style(
                        'xs_product_template',
                        plugins_url('style/archive.min.css', __FILE__)
                );
                $output .= '<div class="products_table">';
                foreach($archive as $single) {
                        $image = get_the_post_thumbnail_url( $single, 'medium' );
                        $title = get_the_title($single);
                        $link = get_the_permalink($single);
                        $price = apply_filters('xs_cart_item_price', $single->ID);
                        $descr = get_post_meta(
                                $single->ID,
                                'xs_products_descr_'.$user_lang,
                                true
                        );

                        $output .= '<a href="'.$link.'">';
                        $output .= '<div class="products_item">';
                        $output .= '<div class="text">';
                        $output .= '<h1>'.$title.'</h1>';
                        $output .= '<p>'.$descr.'</p>';
                        $output .= '</div>';
                        if(!empty($price)) {
                                $output .= '<div class="price">';
                                $output .= '<p>Al prezzo mensile di:</p>';
                                $output .= '<i>'.$price['price'].
                                ' '.$price['currency_symbol'].'</i>';
                                $output .= '</div>';
                        }
                        $output .= '<img src="'.$image.'"/></div></a>';
                }
                $output .= '</div>';
                return $output;
        }

        function show_cart_html($sale_order)
        {
                wp_enqueue_style('xs_cart_checkout_style', plugins_url('style/cart.css', __FILE__));

                $output = '';
                $table = array();

                $symbol = $sale_order['currency_symbol'];

                foreach($sale_order['items'] as $id => $values) {
                        $table[$id]['id'] = $values['id'];
                        $table[$id]['name'] = $values['name'];
                        $table[$id]['quantity'] = $values['quantity'];
                        $table[$id]['price'] = $values['price'] . ' ' . $symbol;
                        $table[$id]['actions'] = '<a href="?rem_cart='.$values['id'].'">Remove</a>';
                }

                $output .= xs_framework::create_table([
                        'data' => $table,
                        'headers' => [
                                'ID',
                                'Descrizione',
                                'Quantità',
                                'Prezzo unitario',
                                'Azioni',
                        ],
                        'echo' => FALSE
                ]);

                $t['subtotal'][0] = 'Imponibile:';
                $t['subtotal'][1] = $sale_order['untaxed'] . ' ' . $symbol;
                $t['taxed'][0] = 'IVA:';
                $t['taxed'][1] = $sale_order['taxed'] . ' ' . $symbol;
                $t['total'][0] = 'Totale:';
                $t['total'][1] = $sale_order['total'] . ' ' . $symbol;
                $output .= xs_framework::create_table([
                        'data' => $t,
                        'echo' => FALSE
                ]);

                $output .= '<form action="" method="GET">';

                $label = '<span>Codice Sconto:</span>';
                $discount = xs_framework::create_input([
                        'name' => 'discount'
                ]);
                $button = xs_framework::create_button([
                        'text' => 'Applica lo sconto'
                ]);

                $output .= xs_framework::create_container([
                        'class' => 'xs_cart_discount',
                        'obj' => [$label, $discount, $button],
                        'echo' => FALSE
                ]);
                $output .= '</form>';

                return $output;
        }

        function show_cart_empty_html()
        {
                wp_enqueue_style(
                        'xs_cart_checkout_style',
                        plugins_url('style/cart.min.css', __FILE__)
                );

                $output = '';
                $output .= '<h2>Il carrello è vuoto!</h2>';
                return $output;
        }

        function show_cart_approved_html($info)
        {
                wp_enqueue_style(
                        'xs_cart_checkout_style',
                        plugins_url('style/cart.min.css', __FILE__)
                );

                $output = '';
                $output .= '<h2>Il pagamento è stato eseguito con successo!</h2>';
                $output .= '<embed src="'.$info['invoice_url'].'" type="application/pdf"
                        class="xs_cart_pdf_frame"></iframe>';
                return $output;
        }

}

endif;

$xs_products_ecommerce_plugin = new xs_products_ecommerce_plugin();

?>
