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
                        'title' => '<i class="fas fa-shopping-cart"></i><span>Carrello</span>',
                        'url' => $this->checkout,
                        'order' => 101,
                        'parent' => $top->ID
                ]);

                /* If user is logged print Logout item, else Login item*/
                if(is_user_logged_in()) {
                        $items[] = xs_framework::insert_nav_menu_item([
                                'title' => '<i class="fas fa-file-invoice"></i>Fatture</span>',
                                'url' => $this->checkout.'?invoice',
                                'order' => 102,
                                'parent' => $top->ID
                        ]);
                        $items[] = xs_framework::insert_nav_menu_item([
                                'title' => '<i class="fas fa-sign-out-alt"></i>Logout</span>',
                                'url' => wp_logout_url( home_url() ),
                                'order' => 103,
                                'parent' => $top->ID
                        ]);
                } else {
                        $items[] = xs_framework::insert_nav_menu_item([
                                'title' => '<i class="fas fa-sign-in-alt"></i><span>Login</span>',
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
                        'text' => 'Aggiungi al Carrello'
                ]);
                /* Create a number input as quantity of this item */
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
        /*
        *  string : show_cart_html : array
        *  This method is used to create the html page for no empty cart
        */
        function show_cart_html($so)
        {
                /* Add the css style */
                wp_enqueue_style('xs_cart_checkout_style', plugins_url('style/cart.css', __FILE__));

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
                        $tmp[] = '<a href="?rem_cart='.$item['id'].'">Rimuovi</a>';

                        $display_items[] = $tmp;
                }
                /* Print the table */
                $output .= xs_framework::create_table([
                        'class' => 'items',
                        'data' => $display_items,
                        'headers' => [
                                'Descrizione',
                                'Quantità',
                                'Prezzo unitario',
                                'Sconto (%)',
                                'IVA',
                                'Subtotale',
                                'Azioni'
                        ],
                        'echo' => FALSE
                ]);
                /* Get the global property from sale order */
                $t['subtotal'][0] = '<strong>Imponibile:</strong>';
                $t['subtotal'][1] = $so['transaction']['subtotal'] . ' ' . $symbol;
                $t['taxed'][0] = '<strong>IVA:</strong>';
                $t['taxed'][1] = $so['transaction']['tax'] . ' ' . $symbol;
                $t['total'][0] = '<strong>Totale:</strong>';
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
                $label = '<span>Codice Sconto:</span>';
                $discount = xs_framework::create_input([
                        'name' => 'discount'
                ]);
                /* Print the button */
                $button = xs_framework::create_button([
                        'text' => 'Applica lo sconto'
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
                $output .= '<h2>Il carrello è vuoto!</h2>';
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
                $output .= '<h2>Il pagamento è stato eseguito con successo!</h2>';
                /* Print the invoice pdf on a frame */
                $output .= '<iframe src="data:application/pdf;base64,'.$info['invoice']['pdf'].'"
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
                                'text' => 'Apri',
                                'echo' => FALSE
                        ]);

                        $display[] = $tmp;
                }

                /* Print the table */
                $output .= xs_framework::create_table([
                        'class' => 'invoice_list',
                        'data' => $display,
                        'headers' => [
                                'ID',
                                'Nome',
                                'Data',
                                'Intestatario',
                                'Importo',
                                'Azioni',
                        ],
                        'echo' => FALSE
                ]);
                return $output;
        }

        function show_cart_invoice($info)
        {
                if(!is_array($info)) {
                        if($info === 0)
                                return '<h1>La fattura selezionata non esiste!</h1>';
                        if($info === 1)
                                return '<h1>Non hai i permessi per entrare qui!</h1>';
                }
                /* Print the HTML */
                $output = '';
                /* Print the invoice pdf on a frame */
                $output .= '<iframe src="data:application/pdf;base64,'.$info['pdf']['base64'].'"
                        style="width:100%;height:500px;"></iframe>';
                /* Return the HTML */
                return $output;
        }

        function print_invoice($info)
        {
                $symbol = $info['transaction']['currency_symbol'];

                $output = '<html>
                <head>
                <meta charset="utf-8"/>
                <meta name="viewport" content="initial-scale=1"/>
                <title>'.$info['invoice']['name'].'</title>
                </head>
                <body>
                <main class="container">
                <div class="header">
                <img alt="Logo" src="data:image/svg+xml;base64,'.$info['company']['logo'].'"/>
                <div style="margin-top:4px;border-bottom: 1px solid black;"></div>
                <div class="company">
                <span itemprop="name">'.$info['company']['name'].'</span><br/>
                <span itemprop="streetAddress">'.$info['company_address']['line1'].'<br/>'.
                $info['company_address']['city'].' '.$info['company_address']['state_code'].' '.
                $info['company_address']['zip'].'<br/>'.
                $info['company_address']['country_code'].'</span>
                </div>
                <div class="payer">
                <span itemprop="name">'.$info['payer']['first_name'].' '.
                $info['payer']['last_name'].'</span><br/>
                <span itemprop="streetAddress">'.$info['invoice_address']['line1'].'<br/>'.
                $info['invoice_address']['city'].' '.$info['invoice_address']['state_code'].' '.
                $info['invoice_address']['zip'].'<br/>'.
                $info['invoice_address']['country_code'].'</span>
                </div>
                </div>
                <div class="page">
                    <h2>
                        <span>Fattura</span>
                        <span>'.$info['invoice']['name'].'</span>
                    </h2>
                    <div class="information">
                        <div name="invoice_date">
                            <strong>Data fattura:</strong>
                            <p>'.$info['invoice']['date_invoice'].'</p>
                        </div>
                        <div name="due_date">
                            <strong>Scadenza:</strong>
                            <p>'.$info['invoice']['date_due'].'</p>
                        </div>
                        <div name="origin">
                            <strong>Origine:</strong>
                            <p>'.$info['invoice']['origin'].'</p>
                        </div>
                        <div name="reference">
                            <strong>Riferimento:</strong>
                            <p>'.$info['invoice']['reference'].'</p>
                        </div>
                    </div>
                </div>';

                foreach($info['items'] as $item) {
                        $tmp = array();
                        $tmp[] = $item['name'];
                        $tmp[] = $item['quantity'];
                        $tmp[] = $item['price'];
                        $tmp[] = $item['discount'];
                        $tmp[] = $item['tax_code'];
                        $tmp[] = $item['subtotal'] . ' ' . $symbol;

                        $display_items[] = $tmp;
                }
                $output .= xs_framework::create_table([
                        'class' => 'items',
                        'data' => $display_items,
                        'headers' => [
                                'Descrizione',
                                'Quantità',
                                'Prezzo unitario',
                                'Sconto (%)',
                                'IVA',
                                'Subtotale'
                        ],
                        'echo' => FALSE
                ]);

                $t['subtotal'][0] = '<strong>Imponibile:</strong>';
                $t['subtotal'][1] = $info['transaction']['subtotal'] . ' ' . $symbol;
                $t['taxed'][0] = '<strong>IVA:</strong>';
                $t['taxed'][1] = $info['transaction']['tax'] . ' ' . $symbol;
                $t['total'][0] = '<strong>Totale:</strong>';
                $t['total'][1] = $info['transaction']['total'] . ' ' . $symbol;
                $output .= xs_framework::create_table([
                        'class' => 'globals',
                        'data' => $t,
                        'echo' => FALSE
                ]);

                $output .= '</div><div class="footer" style="border-top: 1px solid black;">
                <ul class="list-inline">
                    <li>Telefono: <span>'.$info['company']['phone'].'</span></li>
                    <li>Email: <span>'.$info['company']['email'].'</span></li>
                    <li>Web: <span>'.$info['company']['website'].'</span></li>
                </ul>

                </div>
                </main>
                </body>';
                $output .= '<style>
                .container{
                        max-width: 1140px;
                        width: 100%;
                        padding-right: 15px;
                        padding-left: 15px;
                        margin-right: auto;
                        margin-left: auto;
                }
                .header > img{
                        max-height: 45px;
                }
                .payer{
                        margin-left: auto;
                        flex: 0 0 41.66666667%;
                        max-width: 41.66666667%;
                }
                .information{
                        margin-bottom: 32px;
                        margin-top: 32px;
                        display: -webkit-box;
                        display: -webkit-flex;
                        display: flex;
                        flex-wrap: wrap;
                        margin-right: -15px;
                        margin-left: -15px;
                }
                .information > div {
                        padding-right: 16px;
                        padding-left: 16px;
                        display:inline;
                        overflow-wrap: normal;
                }
                strong {
                        font-weight: bolder;
                }
                .information > div > p {
                        margin: 0;
                }
                th{
                        padding-top: 0.75rem;
                        vertical-align: top;
                        border-top: 2px solid #dee2e6;
                        border-bottom: 2px solid #dee2e6;
                        text-align: left;
                        padding-bottom: 0.75rem;
                }
                td{
                        padding: 0.3rem;
                }
                tr{
                        border-bottom: 1px solid #dee2e6;
                }
                .items{
                        width: 100%;
                        margin-bottom: 1rem;
                        border-collapse: collapse;
                }
                .globals{
                        float:right;
                        width: 50%;
                        margin-bottom: 1rem;
                        border-collapse: collapse;
                }
                .list-inline{
                        margin-bottom: 4px;
                        padding-left: 0;
                        list-style: none;
                        margin-top: 0;
                        text-align: center;
                }
                .list-inline > * {
                        display: inline-block;
                        margin-right: 0.5rem;
                }
                .footer {
                        position: absolute;
                        bottom: 0;
                        left: 0;
                        width: 100%;
                }
                body{
                        margin: 0;
                        font-family: "Noto", "Lucida Grande", Helvetica, Verdana, Arial, sans-serif;
                        font-size: 1rem;
                        font-weight: 400;
                        line-height: 1.5;
                        color: #212529;
                        text-align: left;
                        background-color: #FFFFFF;
                        height: 100%;
                }
                </style>';

                $output .= '</html>';

                $invoice_dir='/tmp/xs_invoices/';
                if(is_dir($invoice_dir) === FALSE)
                        mkdir($invoice_dir,0744);

                $htmlpath = $invoice_dir.$info['invoice']['id'].'.html';
                $pdfpath = $invoice_dir.$info['invoice']['id'].'.pdf';

                $htmlfile = fopen($htmlpath , "w") or die("Unable to open file!");
                fwrite($htmlfile, $output);
                fclose($htmlfile);

                exec('wkhtmltopdf '.$htmlpath.' '.$pdfpath);

                unlink($htmlpath);

                $base64 = base64_encode(file_get_contents($pdfpath));

                unlink($pdfpath);

                return $base64;
        }

}

endif;

$xs_products_ecommerce_plugin = new xs_products_ecommerce_plugin();

?>
