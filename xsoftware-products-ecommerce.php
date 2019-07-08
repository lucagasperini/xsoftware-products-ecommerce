<?php
/*
Plugin Name: XSoftware Template E-Commerce
Description: XSoftware Template for xsoftware.it website.
Version: 1.0
Author: Luca Gasperini
Author URI: https://xsoftware.it/
Text Domain: xs_tmp
*/

if(!defined("ABSPATH")) die;

if (!class_exists("xs_template_html_plugin")) :

class xs_template_html_plugin
{

        private $options = array( );


        public function __construct()
        {
                $cart_option = get_option('xs_options_cart');
                $this->checkout = $cart_option['sys']['checkout'];

                add_filter('xs_cart_invoice_pdf_print', [$this, 'print_invoice']);
                add_filter('xs_cart_show_invoice_html', [$this, 'show_cart_invoice']);
                /* Create a filter to show current the sale order */
                add_filter('xs_cart_sale_order_html', [$this, 'show_cart_html']);
                /* Create a filter to print Add to Cart button in wordpress */
                add_filter('xs_cart_add_html', [$this,'cart_add_html']);
                /* Create a filter to show payment approved page */
                add_filter('xs_cart_approved_html', [$this,'show_cart_approved_html']);
                /* Create a filter to show empty cart page */
                add_filter('xs_cart_empty_html', [$this, 'show_cart_empty_html']);
                add_filter('xs_cart_show_list_invoice_html', [$this, 'show_list_invoice']);
                add_filter('xs_product_archive_html', [ $this, 'archive_html' ], 0, 2);
                add_filter('xs_product_single_html', [ $this, 'single_html' ], 0, 2);
                /* Use @xs_framework_menu_items to print cart menu item */
                add_filter('xs_framework_menu_items', [ $this, 'cart_menu_item' ], 2);
                add_action( 'plugins_loaded', [ $this, 'l10n_load' ] );
        }

        function l10n_load()
        {
                load_plugin_textdomain('xs_tmp', false, basename( dirname( __FILE__ ) ).'/l10n/');
        }

        /*
        *  array : cart_menu_item : array
        *  This method is used to create the menu items
        *  using menu class build in on wordpress
        *  $items are the menu class defined on this wordpress installation
        */
        function cart_menu_item($items)
        {
                /* Add the css */
                wp_enqueue_style(
                        'xs_cart_menu_items',
                        plugins_url('style/menu.min.css',__FILE__)
                );

                /* Add a parent menu item for user */
                $top = xs_framework::insert_nav_menu_item([
                        'title' => '<i class="fas fa-user-circle"></i>',
                        'url' => '',
                        'order' => 100
                ]);
                /* Append this menu on input array */
                $items[] = $top;

                /* Add a child menu item for shopping cart */
                $items[] = xs_framework::insert_nav_menu_item([
                        'title' => '<i class="fas fa-shopping-cart"></i>
                                <span>'.__('Cart','xs_tmp').'</span>',
                        'url' => $this->checkout,
                        'order' => 101,
                        'parent' => $top->ID
                ]);

                /* If user is logged print Logout item, else Login item*/
                if(is_user_logged_in()) {

                        $items[] = xs_framework::insert_nav_menu_item([
                                'title' => '<i class="fas fa-file-invoice"></i>
                                        <span>'.__('Invoices','xs_tmp').'</span>',
                                'url' => $this->checkout.'?invoice',
                                'order' => 102,
                                'parent' => $top->ID
                        ]);
                        $items[] = xs_framework::insert_nav_menu_item([
                                'title' => '<i class="fas fa-sign-out-alt"></i>
                                        <span>'.__('Logout','xs_tmp').'</span>',
                                'url' => wp_logout_url( home_url() ),
                                'order' => 103,
                                'parent' => $top->ID
                        ]);
                } else {
                        $items[] = xs_framework::insert_nav_menu_item([
                                'title' => '<i class="fas fa-sign-in-alt"></i>
                                        <span>'.__('Login','xs_tmp').'</span>',
                                'url' => wp_login_url( home_url() ),
                                'order' => 102,
                                'parent' => $top->ID
                        ]);
                }

                /* Return modify menu class array */
                return $items;
        }

        /*
        *  string : cart_add_html : int
        *  This method is used to create the add to cart button
        *  $post_id is the current post id to add on cart
        */
        function cart_add_html($post_id)
        {
                /* Initialize string HTML variable */
                $output = '';

                /* Add the css */
                wp_enqueue_style(
                        'xs_product_template',
                        plugins_url('style/single.min.css',__FILE__)
                );

                /* Create a button with $post_id as GET value*/
                $btn = xs_framework::create_button([
                        'name' => 'add_cart',
                        'value' => $post_id,
                        'text' => __('Add to Cart','xs_tmp')
                ]);
                /* Create a number input as quantity of this item */
                $qt_label = '<span>'.__('Months','xs_tmp').':</span>';
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

                /* Create a form on checkout page as GET method */
                $output .= '<form action="'.$this->checkout.'" method="get">';
                /* Get HTML string as container of css class */
                $output .= xs_framework::create_container([
                        'class' => 'xs_add_cart_container',
                        'obj' => [$qt_container, $btn],
                        'echo' => FALSE
                ]);
                /* Close the form */
                $output .= '</form>';
                /* Return HTML */
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
                        echo '<span>'.__('Price','xs_tmp').':</span>';
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
                                $output .= '<p>'.__('Price per month','xs_tmp').':</p>';
                                $output .= '<i>'.$price['price'].
                                ' '.$price['currency_symbol'].'</i>';
                                $output .= '</div>';
                        }
                        $output .= '<img src="'.$image.'"/></div></a>';
                }
                $output .= '</div>';
                return $output;
        }

        /*
        *  string : show_cart_html : array
        *  This method is used to create the html page for no empty cart
        */
        function show_cart_html($so)
        {
                /* Add the css style */
                wp_enqueue_style(
                        'xs_cart_checkout_style',
                        plugins_url('style/cart.css', __FILE__)
                );
                /* Print the HTML */
                $output = '';
                /* Create table array */
                $table = array();

                /* Get the currency symbol */
                $symbol = $so['transaction']['currency_symbol'];

                /* Get the variable for sale order */
                foreach($so['items'] as $item) {
                        $tmp = array();
                        $tmp[] = $item['name'];
                        $tmp[] = $item['quantity'];
                        $tmp[] = $item['price'];
                        $tmp[] = $item['discount'];
                        $tmp[] = $item['tax_code'];
                        $tmp[] = $item['subtotal'] . ' ' . $symbol;
                        $tmp[] = '<a href="?rem_cart='.$item['id'].'">'.
                                __('Remove','xs_tmp').'</a>';

                        $display_items[] = $tmp;
                }
                /* Print the table */
                $output .= xs_framework::create_table([
                        'class' => 'items',
                        'data' => $display_items,
                        'headers' => [
                                __('Description','xs_tmp'),
                                __('Quantity','xs_tmp'),
                                __('Price','xs_tmp'),
                                __('Discount (%)','xs_tmp'),
                                __('VAT','xs_tmp'),
                                __('Subtotal','xs_tmp'),
                                __('Actions','xs_tmp')
                        ],
                        'echo' => FALSE
                ]);

                /* Get the global property from sale order */
                $t['subtotal'][0] = '<strong>'.__('Subtotal','xs_tmp').':</strong>';
                $t['subtotal'][1] = $so['transaction']['subtotal'] . ' ' . $symbol;
                $t['taxed'][0] = '<strong>'.__('VAT','xs_tmp').':</strong>';
                $t['taxed'][1] = $so['transaction']['tax'] . ' ' . $symbol;
                $t['total'][0] = '<strong>'.__('Total','xs_tmp').':</strong>';
                $t['total'][1] = $so['transaction']['total'] . ' ' . $symbol;
                /* Get the table */
                $output .= xs_framework::create_table([
                        'class' => 'globals',
                        'data' => $t,
                        'echo' => FALSE
                ]);

                /* Get the form for discount code */
                $output .= '<form action="" method="GET">';
                /* Print discount code label and text input */
                $label = '<span>'.__('Discount Code','xs_tmp').':</span>';
                $discount = xs_framework::create_input([
                        'name' => 'discount'
                ]);
                /* Print the button */
                $button = xs_framework::create_button([
                        'text' => __('Apply discount','xs_tmp')
                ]);
                /* Print the container */
                $output .= xs_framework::create_container([
                        'class' => 'xs_cart_discount',
                        'obj' => [$label, $discount, $button],
                        'echo' => FALSE
                ]);
                /* Close the form */
                $output .= '</form>';
                /* Return the HTML string */
                return $output;
        }
        /*
        *  string : cart_add_html : void
        *  This method is used to create the html page for empty cart
        */
        function show_cart_empty_html()
        {
                /* Add the css style */
                wp_enqueue_style(
                        'xs_cart_checkout_style',
                        plugins_url('style/cart.min.css', __FILE__)
                );
                /* Print the HTML */
                $output = '';
                $output .= '<h2>'.__('The cart is empty!','xs_tmp').'</h2>';
                /* Return the HTML */
                return $output;
        }
        /*
        *  string : show_cart_approved_html : array
        *  This method is used to create the html page for approved payment
        */
        function show_cart_approved_html($info)
        {
                /* Add the css style */
                wp_enqueue_style(
                        'xs_cart_checkout_style',
                        plugins_url('style/cart.min.css', __FILE__)
                );

                /* Print the HTML */
                $output = '';
                $output .= '<h2>'.__('The payment was successful!','xs_tmp').'</h2>';
                /* Print the invoice pdf on a frame */
                $output .= '<iframe src="data:application/pdf;base64,'.$info['pdf']['base64'].'"
                        class="xs_cart_pdf_frame"></iframe>';
                /* Return the HTML */
                return $output;
        }

        function show_list_invoice($info)
        {
                $output = '';
                $display = array();

                foreach($info as $i) {
                        $symbol = $i['transaction']['currency_symbol'];
                        $tmp = array();
                        $tmp[] = $i['invoice']['id'];
                        $tmp[] = $i['invoice']['name'];
                        $tmp[] = $i['invoice']['date'];
                        $tmp[] = $i['payer']['name'];
                        $tmp[] = $i['transaction']['total'] . ' ' . $symbol;
                        $tmp[] = xs_framework::create_link([
                                'href' => $this->checkout.'?invoice='.$i['invoice']['id'],
                                'text' => __('Open','xs_tmp'),
                                'echo' => FALSE
                        ]);

                        $display[] = $tmp;
                }

                /* Print the table */
                $output .= xs_framework::create_table([
                        'class' => 'invoice_list',
                        'data' => $display,
                        'headers' => [
                                __('ID','xs_tmp'),
                                __('Name','xs_tmp'),
                                __('Date','xs_tmp'),
                                __('Accountholder','xs_tmp'),
                                __('Amount','xs_tmp'),
                                __('Actions','xs_tmp'),
                        ],
                        'echo' => FALSE
                ]);
                return $output;
        }

        function show_cart_invoice($info)
        {
                if(!is_array($info)) {
                        if($info === 0)
                                return '<h1>'.__(
                                        'The selected invoice does not exist!','xs_tmp').'</h1>';
                        if($info === 1)
                                return '<h1>'.__('You do not have permission to log in here!',
                                        'xs_tmp').'</h1>';
                }
                /* Print the HTML */
                $output = '';
                /* Print the invoice pdf on a frame */
                $output .= '<iframe src="data:application/pdf;base64,'.$info['pdf']['base64'].'"
                        style="width:100%;height:500px;"></iframe>';
                /* Return the HTML */
                return $output;
        }

}

endif;

$xs_template_html_plugin = new xs_template_html_plugin();

?>
