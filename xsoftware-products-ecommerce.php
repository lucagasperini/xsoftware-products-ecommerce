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
                $output .= '<iframe src="'.$this->show_invoice($info).'" type="application/pdf"
                        class="xs_cart_pdf_frame"></iframe>';
                return $output;
        }

        function show_invoice($info)
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
                $info['company_address']['city'].' '.$info['company_address']['state'].' '.
                $info['company_address']['zip'].'<br/>'.
                $info['company_address']['country_code'].'</span>
                </div>
                <div class="payer">
                <span itemprop="name">'.$info['payer']['first_name'].' '.
                $info['payer']['last_name'].'</span><br/>
                <span itemprop="streetAddress">'.$info['invoice_address']['line1'].'<br/>'.
                $info['invoice_address']['city'].' '.$info['invoice_address']['state'].' '.
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
                        border-top: 1px solid #dee2e6;
                        border-bottom: 1px solid #dee2e6;
                        text-align: left;
                        padding-bottom: 0.75rem;
                }
                td{
                        padding: 0.3rem;
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
                .globals > tbody > tr{
                        border-top: 1px solid #dee2e6;
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

                $invoice_dir=XS_CONTENT_DIR.'invoices/';
                if(is_dir($invoice_dir) === FALSE)
                        mkdir($invoice_dir,0744);

                $htmlpath = $invoice_dir.$info['invoice']['id'].'.html';
                $pdfpath = $invoice_dir.$info['invoice']['id'].'.pdf';

                $htmlfile = fopen($htmlpath , "w") or die("Unable to open file!");
                fwrite($htmlfile, $output);
                fclose($htmlfile);

                exec('wkhtmltopdf '.$htmlpath.' '.$pdfpath);

                unlink($htmlpath);

                return xs_framework::get_content_file_url($pdfpath);
        }

}

endif;

$xs_products_ecommerce_plugin = new xs_products_ecommerce_plugin();

?>
