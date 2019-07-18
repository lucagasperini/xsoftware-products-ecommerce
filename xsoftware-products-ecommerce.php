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
                add_filter('xs_product_archive_html_hosting', [$this, 'archive_html_hosting'], 0,2);
                add_filter('xs_product_archive_html_software',[$this, 'archive_html_software'],0,2);
                add_filter('xs_product_single_html_hosting', [$this, 'single_html_hosting'], 0,2);
                add_filter('xs_product_single_html_software', [$this, 'single_html_software'], 0,2);
                /* Use @xs_framework_menu_items to print cart menu item */
                add_filter('xs_framework_menu_items', [ $this, 'cart_menu_item' ], 2);
                add_action( 'plugins_loaded', [ $this, 'l10n_load' ] );
                add_filter('xs_socials_facebook_post', [$this, 'show_facebook_post'], 10 ,2);
                add_filter('xs_socials_twitter_post', [$this, 'show_twitter_post']);
                add_filter('xs_framework_privacy_show', [$this, 'show_privacy_banner']);
                add_filter('xs_bugtracking_searchbox_show', [$this, 'bugtracking_searchbox_show']);
                add_filter('xs_bugtracking_archive_show', [$this, 'bugtracking_archive_show'], 10, 2);
                add_filter('xs_bugtracking_single_show', [$this, 'bugtracking_single_show'], 10, 2);
        }

        function l10n_load()
        {
                load_plugin_textdomain('xs_tmp', false, basename( dirname( __FILE__ ) ).'/l10n/');
        }

        function show_privacy_banner()
        {
                /* Add the css */
                wp_enqueue_style(
                        'xs_privacy_banner_style',
                        plugins_url('style/privacy.min.css',__FILE__)
                );
                $url = get_privacy_policy_url();
                $message = __('This site uses technical and third-party cookies, to improve the experience of '.
                'navigation, to use them press the "Accept" button. For more information you can consult our','xs_tmp').
                '<a href="'.$url.'"> '.__('Privacy Policy','xs_tmp').'</a>.';

                return '<div class="xs_privacy_message">
                <p>'.$message.'</p>
                <button onclick="xs_privacy_accept();">'.__('Accept','xs_tmp').'</button>
                </div>';
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

        function single_html_software($id, $single)
        {
                wp_enqueue_style(
                        'xs_product_template',
                        plugins_url('style/single.min.css', __FILE__)
                );

                $output = '';
                $image = get_the_post_thumbnail_url( $id, 'medium' );
                $title = get_the_title($id);

                $output .= '<div class="product_item">';
                $output .= '<div class="product_content">';
                $output .= '<img src="'.$image.'"/>';
                $output .= '<div class="info">';
                $output .= '<h1>'.$title.'</h1>';
                $output .= '<p class="descr">'.$single['descr'].'</p>';
                $output .= '<p class="text">'.$single['text'].'</p>';
                $output .= '</div>';
                $output .= '</div>';
                $output .= '</div>';

                return $output;
        }

        function single_html_hosting($id, $single)
        {
                wp_enqueue_style(
                        'xs_product_template',
                        plugins_url('style/single.min.css', __FILE__)
                );
                $output = '';
                $image = get_the_post_thumbnail_url( $id, 'medium' );
                $title = get_the_title($id);
                $price = apply_filters('xs_cart_item_price', $id);

                $output .= '<div class="product_item">';
                $output .= '<div class="product_content">';
                $output .= '<img src="'.$image.'"/>';
                $output .= '<div class="info">';
                $output .= '<h1>'.$title.'</h1>';
                $output .= '<p class="descr">'.$single['descr'].'</p>';
                $output .= '<div class="service">';
                $output .= '<h3>'.__('Our service includes','xs_tmp').':</h3>';
                if(!empty($single['ssl'])) {
                        $output .= '<b>'.__('SSL certificate','xs_tmp').':</b>';
                        $output .= '<p>'.__('Included','xs_tmp').'</p>';
                }
                if(!empty($single['backup'])) {
                        $output .= '<b>'.__('Daily backup','xs_tmp').':</b>';
                        $output .= '<p>'.__('Included','xs_tmp').'</p>';
                }
                if(!empty($single['dns'])) {
                        $output .= '<b>'.__('DNS management','xs_tmp').':</b>';
                        $output .= '<p>'.__('Included','xs_tmp').'</p>';
                }
                if(!empty($single['web'])) {
                        $output .= '<b>'.__('Website','xs_tmp').':</b>';
                        $output .= '<p>Hosting WordPress '.$single['web'].' SUPER GIGA</p>';
                }
                if(!empty($single['cloud'])) {
                        $output .= '<b>'.__('Cloud','xs_tmp').':</b>';
                        $output .= '<p>Hosting NextCloud '.$single['cloud'].' GIGA</p>';
                }
                if(!empty($single['erp'])) {
                        $output .= '<b>'.__('ERP','xs_tmp').':</b>';
                        $output .= '<p>Hosting Odoo '.$single['erp'].' SUPER GIGA</p>';
                }
                if(!empty($single['mail'])) {
                        $output .= '<b>'.__('E-Mail','xs_tmp').':</b>';
                        $output .= '<p>Hosting E-Mail '.$single['mail'].' GIGA</p>';
                }
                $output .= '</div>';
                $output .= '<div class="server">';
                $output .= '<h3>'.__('Our server provides','xs_tmp').':</h3>';
                if(!empty($single['ssd'])) {
                        $output .= '<b>'.__('SSD Drive','xs_tmp').':</b>';
                        $output .= '<p>'.$single['ssd'].' GB</p>';
                }
                if(!empty($single['hdd'])) {
                        $output .= '<b>'.__('HDD Drive','xs_tmp').':</b>';
                        $output .= '<p>'.$single['hdd'].' GB</p>';
                }
                if(!empty($single['ram'])) {
                        $output .= '<b>'.__('RAM Space','xs_tmp').':</b>';
                        $output .= '<p>'.$single['ram'].' MB</p>';
                }
                if(!empty($single['core'])) {
                        $output .= '<b>'.__('CPU Cores','xs_tmp').':</b>';
                        $output .= '<p>'.$single['core'].' Core</p>';
                }
                $output .= '</div>';
                $output .= '</div>';
                if(!empty($price)) {
                        $output .= '<div class="cart">';
                        $output .= '<span>'.__('Price','xs_tmp').':</span>';
                        $output .= '<i>'.$price['price'].' '.$price['currency_symbol'].'</i>';
                        $output .= apply_filters('xs_cart_add_html', $id);
                        $output .= '</div>';
                }
                $output .= '</div>';
                $output .= '</div>';

                return $output;
        }

        function compare_by_price($a, $b)
        {
                if ($a['price'] == $b['price']) {
                        return 0;
                }
                return ($a['price'] < $b['price']) ? -1 : 1;
        }

        function archive_html_software($archive, $user_lang)
        {
                $output = '';
                wp_enqueue_style(
                        'xs_product_template',
                        plugins_url('style/archive.min.css', __FILE__)
                );
                $output .= '<div class="products_table">';

                foreach($archive as $id) {
                        $image = get_the_post_thumbnail_url( $id, 'medium' );
                        $title = get_the_title($id);
                        $link = get_the_permalink($id);
                        $descr = get_post_meta(
                                $id,
                                'xs_products_descr_'.$user_lang,
                                true
                        );

                        $output .= '<a href="'.$link.'">';
                        $output .= '<div class="products_item">';
                        $output .= '<div class="text">';
                        $output .= '<h1>'.$title.'</h1>';
                        $output .= '<p>'.$descr.'</p>';
                        $output .= '</div>';
                        $output .= '<img src="'.$image.'"/></div></a>';
                }
                $output .= '</div>';
                return $output;
        }

        function archive_html_hosting($archive, $user_lang)
        {
                $output = '';
                wp_enqueue_style(
                        'xs_product_template',
                        plugins_url('style/archive.min.css', __FILE__)
                );
                $by_price = array();

                foreach($archive as $id) {
                        $tmp = array();

                        $tmp['id'] = $id;
                        $price = apply_filters('xs_cart_item_price', $id);
                        $tmp['price'] = $price['price'];
                        $tmp['currency'] = $price['currency_symbol'];
                        $tmp['link'] = get_the_permalink($id);
                        $tmp['title'] = get_the_title($id);
                        $tmp['image'] = get_the_post_thumbnail_url( $id, 'medium' );
                        $tmp['descr'] = get_post_meta(
                                $id,
                                'xs_products_descr_'.$user_lang,
                                true
                        );

                        $by_price[] = $tmp;
                }

                usort($by_price, [$this,'compare_by_price']);

                $output .= '<div class="products_table">';

                foreach($by_price as $s) {
                        $output .= '<a href="'.$s['link'].'">';
                        $output .= '<div class="products_item">';
                        $output .= '<div class="text">';
                        $output .= '<h1>'.$s['title'].'</h1>';
                        $output .= '<p>'.$s['descr'].'</p>';
                        $output .= '</div>';
                        if(!empty($price)) {
                                $output .= '<div class="price">';
                                $output .= '<p>'.__('Price per month','xs_tmp').':</p>';
                                $output .= '<i>'.$s['price'].
                                ' '.$s['currency'].'</i>';
                                $output .= '</div>';
                        }
                        $output .= '<img src="'.$s['image'].'"/></div></a>';
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

        function show_facebook_post($post, $user)
        {
                $post += [
                        'description' => '',
                        'caption' => '',
                        'created_time' => '',
                        'full_picture' => '',
                        'is_published' => '',
                        'permalink_url' => '',
                        'width' => '',
                        'height' => '',
                        'event' => '',
                        'is_hidden' => '',
                        'from' => '',
                        'link' => '',
                        'message_tags' => '',
                        'status_type' => '',
                        'privacy' => ''
                ];

                /* Add the css style */
                wp_enqueue_style(
                        'xs_socials_facebook_style',
                        plugins_url('style/socials.min.css', __FILE__)
                );

                if(empty($post['description']) && empty($post['full_picture']))
                        return '';

                /* Print the HTML */
                $output = '';


                $output .= '<div class="xs_socials_post">';

                $output .= '<div class="info">';
                $output .= '<a href="'.$user['link'].'">';
                $output .= '<div class="user">';
                $output .= '<img src="https://graph.facebook.com/'.$post['from']['id'].'/picture?type=square">';
                $output .= '<span>'.$post['from']['name'].'</span>';
                $output .= '</div>';
                $output .= '</a>';
                $output .= '<span class="date">'.$post['created_time']->format('d/m/Y').'</span>';
                $output .= '</div>';
                $output .= '<a href="'.$post['permalink_url'].'">';
                $output .= '<div class="post">';
                if(!empty($post['description']))
                        $output .= '<span class="description">'.$post['description'].'</span>';
                if(!empty($post['full_picture']))
                        $output .= '<img src="'.$post['full_picture'].'">';
                //if(!empty($post['caption']))
                //        $output .= '<span class="caption">'.$post['caption'].'</span>';
                $output .= '</div>';
                $output .= '</a>';

                $output .= '</div>';

                return $output;
        }

        function show_twitter_post($post)
        {

                /* Add the css style */
                wp_enqueue_style(
                        'xs_socials_twitter_style',
                        plugins_url('style/socials.min.css', __FILE__)
                );

                /* Print the HTML */
                $output = '';


                $output .= '<div class="xs_socials_post">';

                $output .= '<div class="info">';
                $output .= '<a href="'.$post['user_link'].'">';
                $output .= '<div class="user">';
                $output .= '<img src="'.$post['user_image'].'">';
                $output .= '<span>'.$post['user_name'].'</span>';
                $output .= '</div>';
                $output .= '</a>';
                $output .= '<span class="date">'.$post['date'].'</span>';
                $output .= '</div>';
                $output .= '<a href="'.$post['user_link'].'/status/'.$post['id'].'">';
                $output .= '<div class="post">';
                if(!empty($post['description']))
                        $output .= '<span class="description">'.$post['description'].'</span>';
                if(!empty($post['media']))
                        $output .= '<img src="'.$post['media'][0]['url'].'">';
                //if(!empty($post['caption']))
                //        $output .= '<span class="caption">'.$post['caption'].'</span>';
                $output .= '</div>';
                $output .= '</a>';

                $output .= '</div>';

                return $output;
        }

        function bugtracking_searchbox_show($search)
        {
                /* Create the html form */
                echo '<form method="get">';

                xs_framework::create_select([
                        'class' => 'xs_full_width',
                        'name' => 'pro',
                        'data'=> $search['product'],
                        'default' => 'By Product',
                        'echo' => TRUE
                ]);

                xs_framework::create_select([
                        'class' => 'xs_full_width',
                        'name' => 'st',
                        'data'=> $search['status'],
                        'default' => 'By Status',
                        'echo' => TRUE
                ]);

                xs_framework::create_select([
                        'class' => 'xs_full_width',
                        'name' => 'i',
                        'data'=> $search['importance'],
                        'default' => 'By Importance',
                        'echo' => TRUE
                ]);

                xs_framework::create_button([
                        'text' => 'Search..',
                        'echo' => TRUE
                ]);

                /* Close the html form */
                echo "</form>";

                /* Get all variable from the $_GET variable, is empty string if is not present */
                $output['product'] = isset($_GET['pro']) ? intval($_GET['pro']) : '';
                $output['status'] = isset($_GET['st']) ? intval($_GET['st']) : '';
                $output['importance'] = isset($_GET['i']) ? intval($_GET['i']) : '';

                return $output;
        }

        function bugtracking_archive_show($bugs, $search)
        {
                $data = array();
                $users = xs_framework::get_user_display_name();

                /* Loop all post found in the query */
                foreach($bugs as $single) {
                        /* Get the id from the post */
                        $id = $single->ID;
                        /* Get the metadata from the post */
                        $meta = get_post_custom( $id );

                        /* Get the metadata specific informations from the post */
                        $current['product'] = isset($meta['xs_bugtracking_product'][0]) ?
intval($meta['xs_bugtracking_product'][0]) : '';
                        $current['status'] = isset($meta['xs_bugtracking_status'][0]) ?
intval($meta['xs_bugtracking_status'][0]) : '';
                        $current['assignee'] = isset($meta['xs_bugtracking_assignee'][0]) ?
intval($meta['xs_bugtracking_assignee'][0]) : '';
                        $current['importance'] = isset($meta['xs_bugtracking_importance'][0]) ?
intval($meta['xs_bugtracking_importance'][0]) : '';

                        /* Create a link in the actions column */
                        $data[$id][] = xs_framework::create_link([
                                'href' => get_permalink($id),
                                'text' => 'Show'
                        ]);

                        /* Define all property columns */
                        $data[$id][] = $id;
                        $data[$id][] = $search['product'][$current['product']];
                        $data[$id][] = $single->post_title;
                        $data[$id][] = $search['status'][$current['status']];
                        $data[$id][] = $users[$current['assignee']];
                        $data[$id][] = $users[$single->post_author];
                        $data[$id][] = $search['importance'][$current['importance']];
                        $data[$id][] = $single->post_date_gmt;
                        $data[$id][] = $single->post_modified_gmt;
                }
                $fields = array();

                /* Define all columns header */
                $fields[] = 'Actions';
                $fields[] = 'ID';
                $fields[] = 'Product';
                $fields[] = 'Title';
                $fields[] = 'Status';
                $fields[] = 'Assignee';
                $fields[] = 'Reported By';
                $fields[] = 'Importance';
                $fields[] = 'Reported on';
                $fields[] = 'Last edit on';

                /* Create the table */
                return xs_framework::create_table([
                        'headers' => $fields,
                        'data' => $data,
                        'echo' => FALSE
                ]);

        }

        function bugtracking_single_show($id, $search)
        {
                $users = xs_framework::get_user_display_name();
                /* Get the post class from the post id */
                $post = get_post($id);
                /* Get the metadata from the post id */
                $meta = get_post_custom( $id );

                /* Get the metadata specific informations from the post */
                $product = isset($meta['xs_bugtracking_product'][0]) ? intval($meta['xs_bugtracking_product'][0]) : '';
                $status = isset($meta['xs_bugtracking_status'][0]) ? intval($meta['xs_bugtracking_status'][0]) : '';
                $assignee = isset($meta['xs_bugtracking_assignee'][0]) ?
                        intval($meta['xs_bugtracking_assignee'][0]) :
                        '';
                $importance = isset($meta['xs_bugtracking_importance'][0]) ?
                        intval($meta['xs_bugtracking_importance'][0]) :
                        '';

                $data['product'][0] = 'Product:';
                $data['product'][1] = $search['product'][$product];
                $data['status'][0] = 'Status:';
                $data['status'][1] = $search['status'][$status];
                $data['importance'][0] = 'Importance:';
                $data['importance'][1] = $search['importance'][$importance];
                $data['assignee'][0] = 'Assignee:';
                $data['assignee'][1] = $users[$assignee];
                $data['reported_by'][0] = 'Reported by:';
                $data['reported_by'][1] = $users[$post->post_author];
                $data['create_date'][0] = 'Reported on:';
                $data['create_date'][1] = $post->post_date_gmt;
                $data['modify_date'][0] = 'Last edit on:';
                $data['modify_date'][1] = $post->post_modified_gmt;

                /* Print the title of the bug with it's id */
                echo '<h1>Bug '.$id.': '.get_the_title($id).'</h1>';

                /* Print the bug property as table */
                xs_framework::create_table([
                        'data' => $data
                ]);

                /* Print the text of the bug */
                echo '<p class="xs_text">'.$post->post_content.'</p>';
        }

}

endif;

$xs_template_html_plugin = new xs_template_html_plugin();

?>
