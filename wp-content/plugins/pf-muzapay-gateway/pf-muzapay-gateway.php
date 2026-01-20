<?php
/**
 * Plugin Name: PF Muzapay Gateway
 * Description: WooCommerce platebni brana Benefit Plus (Muzapay).
 * Author: PrivateFitness
 * Version: 0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/class-pf-mz-utils.php';

add_action( 'plugins_loaded', 'pf_muzapay_gateway_init', 11 );

function pf_muzapay_gateway_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_Gateway_PF_Muzapay extends WC_Payment_Gateway {
        /**
         * Declare gateway settings explicitly to avoid dynamic properties on PHP 8.2+.
         */
        public $eshop_id;
        public $password;
        public $test_mode;

        public function __construct() {
            $this->id                 = 'pf_muzapay';
            $this->method_title       = __( 'Benefit Plus (Muzapay)', 'pf-muzapay' );
            $this->method_description = __( 'Platebni brana Benefit Plus (Muzapay).', 'pf-muzapay' );
            $this->title              = __( 'Benefit Plus (Muzapay)', 'pf-muzapay' );
            $this->has_fields         = false;
            $this->supports           = array( 'products' );

            $this->init_form_fields();
            $this->init_settings();

            $this->enabled     = $this->get_option( 'enabled', 'yes' );
            $this->title       = $this->get_option( 'title', $this->title );
            $this->description = $this->get_option( 'description', '' );
            $this->eshop_id    = $this->get_option( 'eshop_id', '' );
            $this->password    = $this->get_option( 'password', '' );
            $this->test_mode   = 'yes' === $this->get_option( 'test_mode', 'yes' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            add_action( 'woocommerce_api_pf_muzapay_return', array( $this, 'handle_return' ) );
            add_action( 'woocommerce_api_pf_muzapay_notify', array( $this, 'handle_notify' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'poll_on_thankyou' ), 10, 1 );
            add_action( 'woocommerce_order_actions', array( $this, 'add_admin_action' ) );
            add_action( 'woocommerce_order_action_pf_muzapay_refresh', array( $this, 'handle_admin_action' ) );
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled'     => array(
                    'title'   => __( 'Enable/Disable', 'pf-muzapay' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Povolit platbu Benefit Plus (Muzapay)', 'pf-muzapay' ),
                    'default' => 'yes',
                ),
                'title'       => array(
                    'title'       => __( 'Title', 'pf-muzapay' ),
                    'type'        => 'text',
                    'description' => __( 'Nazev zobrazeny zakaznikovi v pokladne.', 'pf-muzapay' ),
                    'default'     => __( 'Benefit Plus (Muzapay)', 'pf-muzapay' ),
                ),
                'description' => array(
                    'title'       => __( 'Description', 'pf-muzapay' ),
                    'type'        => 'textarea',
                    'description' => __( 'Popis zobrazeny v pokladne.', 'pf-muzapay' ),
                    'default'     => '',
                ),
                'eshop_id'    => array(
                    'title'       => __( 'E-shop ID', 'pf-muzapay' ),
                    'type'        => 'text',
                    'description' => __( 'Identifikator e-shopu poskytnuty Muzapay (env ma prednost).', 'pf-muzapay' ),
                    'default'     => '',
                ),
                'password'    => array(
                    'title'       => __( 'Password / Secret', 'pf-muzapay' ),
                    'type'        => 'password',
                    'description' => __( 'Heslo/API secret poskytnute Muzapay (env ma prednost).', 'pf-muzapay' ),
                    'default'     => '',
                ),
                'test_mode'   => array(
                    'title'       => __( 'Test mode', 'pf-muzapay' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Pouzit testovaci prostredi', 'pf-muzapay' ),
                    'default'     => 'yes',
                ),
            );
        }

        /**
         * INIT call to Muzapay.
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $cfg   = PF_MZ_Utils::cfg();

            $cfg['eshop_id'] = $cfg['eshop_id'] ?: $this->eshop_id;
            $cfg['secret']   = $cfg['secret'] ?: $this->password;

            $pkey = PF_MZ_Utils::load_private_key( $cfg );
            if ( ! $pkey ) {
                wc_add_notice( __( 'Chybi privatni klic pro Muzapay.', 'pf-muzapay' ), 'error' );
                return array( 'result' => 'failure' );
            }

            $token = PF_MZ_Utils::bearer_token( $cfg );
            if ( is_wp_error( $token ) ) {
                wc_add_notice( __( 'Nepodarilo se ziskat token Muzapay.', 'pf-muzapay' ), 'error' );
                return array( 'result' => 'failure' );
            }

            $amount_minor = (int) round( $order->get_total() * 100 ); // 1.00 -> 100
            $cid          = PF_MZ_Utils::correlation_id();
            $payload      = array(
                'amount'             => (string) $amount_minor,
                'productCode'        => $cfg['product_code'],
                'orderReferenceCode' => (string) $order->get_id(),
                'orderDescription'   => 'Order #' . $order->get_order_number(),
                'merchantData'       => base64_encode( (string) $order->get_order_key() ),
                'returnUrl'          => $cfg['return_url'],
                'language'           => $cfg['language'],
            );

            $keys       = array(
                'x-correlation-id',
                'amount',
                'productCode',
                'orderReferenceCode',
                'orderDescription',
                'merchantData',
                'returnUrl',
                'language',
            );
            $sign_data  = array_merge( array( 'x-correlation-id' => $cid ), $payload );
            $canonical  = PF_MZ_Utils::canonical_string( $sign_data, $keys, 'pipe' );
            $signature  = PF_MZ_Utils::sign_rsa( $canonical, $pkey );

            if ( ! $signature ) {
                wc_add_notice( __( 'Nepodarilo se vytvorit podpis pro Muzapay.', 'pf-muzapay' ), 'error' );
                return array( 'result' => 'failure' );
            }

            $init_url = $cfg['endpoints']['init'] . '?signature=' . rawurlencode( $signature );

            PF_MZ_Utils::log( 'INIT payload', array( 'url' => $init_url, 'body' => $payload ) );

            $res = PF_MZ_Utils::post(
                $init_url,
                $payload,
                array(
                    'headers' => array(
                        'Authorization'    => 'Bearer ' . $token,
                        'Content-Type'     => 'application/json; charset=utf-8',
                        'x-correlation-id' => $cid,
                    ),
                )
            );

            if ( is_wp_error( $res ) ) {
                $order->update_status( 'failed', 'Muzapay INIT WP_Error: ' . $res->get_error_message() );
                wc_add_notice( __( 'Platbu se nepodarilo inicializovat. Zkuste to prosim znovu.', 'pf-muzapay' ), 'error' );
                return array( 'result' => 'failure' );
            }

            PF_MZ_Utils::log( 'INIT response', $res );

            if ( (int) $res['code'] !== 200 || empty( $res['json']['paymentId'] ) || empty( $res['json']['gatewayUrl'] ) ) {
                $order->update_status( 'failed', 'Muzapay INIT HTTP ' . $res['code'] );
                wc_add_notice( __( 'Platbu se nepodarilo inicializovat. Zkuste to prosim znovu.', 'pf-muzapay' ), 'error' );
                return array( 'result' => 'failure' );
            }

            $payment_id = $res['json']['paymentId'];
            $redirect   = $res['json']['gatewayUrl'];

            $order->update_meta_data( '_pf_mz_payment_id', $payment_id );
            $order->update_status( 'on-hold', 'Muzapay: cekame na uhradu (init OK)' );
            $order->save();

            return array(
                'result'   => 'success',
                'redirect' => $redirect,
            );
        }

        /**
         * RETURN endpoint hit by user browser.
         */
        public function handle_return() {
            $cfg  = PF_MZ_Utils::cfg();
            $data = wp_unslash( $_REQUEST );

            PF_MZ_Utils::log( 'RETURN hit', $data );

            $order_id   = isset( $data['orderId'] ) ? (int) $data['orderId'] : 0;
            $payment_id = isset( $data['paymentId'] ) ? (string) $data['paymentId'] : '';
            $order      = $order_id ? wc_get_order( $order_id ) : null;

            if ( ! $order && $payment_id ) {
                $orders = wc_get_orders(
                    array(
                        'limit'      => 1,
                        'meta_key'   => '_pf_mz_payment_id',
                        'meta_value' => $payment_id,
                    )
                );
                if ( $orders ) {
                    $order = $orders[0];
                }
            }

            if ( ! $order ) {
                wp_die( 'Order not found', 400 );
            }

            $order->add_order_note( 'Muzapay: uzivatel se vratil z brany' );

            if ( ! $payment_id ) {
                $payment_id = (string) $order->get_meta( '_pf_mz_payment_id' );
            }

            if ( $payment_id ) {
                $this->update_order_from_state( $order, $payment_id, $cfg );
            }

            wp_safe_redirect( $this->get_return_url( $order ) );
            exit;
        }

        /**
         * NOTIFY endpoint hit server-to-server. Updated orders even pokud se uzivatel nevrati.
         */
        public function handle_notify() {
            $cfg  = PF_MZ_Utils::cfg();
            $data = wp_unslash( $_REQUEST );

            PF_MZ_Utils::log( 'NOTIFY hit', $data );

            $order_id   = isset( $data['orderReferenceCode'] ) ? (int) $data['orderReferenceCode'] : 0;
            $payment_id = isset( $data['paymentId'] ) ? (string) $data['paymentId'] : '';

            $order = null;
            if ( $order_id ) {
                $order = wc_get_order( $order_id );
            }
            if ( ! $order && $payment_id ) {
                $orders = wc_get_orders(
                    array(
                        'limit'      => 1,
                        'meta_key'   => '_pf_mz_payment_id',
                        'meta_value' => $payment_id,
                    )
                );
                if ( $orders ) {
                    $order = $orders[0];
                }
            }

            if ( ! $order ) {
                status_header( 400 );
                echo 'order not found';
                exit;
            }

            if ( $payment_id ) {
                $this->update_order_from_state( $order, $payment_id, $cfg );
                $order->add_order_note( 'Muzapay: notify zpracovan' );
            }

            status_header( 200 );
            echo 'OK';
            exit;
        }

        /**
         * Poll Muzapay state and update order.
         */
        protected function update_order_from_state( WC_Order $order, string $payment_id, array $cfg ): void {
            $state = PF_MZ_Utils::fetch_state( $payment_id, $cfg );

            if ( is_wp_error( $state ) ) {
                PF_MZ_Utils::log( 'STATE error', array( 'error' => $state->get_error_message(), 'order' => $order->get_id() ) );
                $order->add_order_note( 'Muzapay: nepodarilo se nacist stav (' . $state->get_error_message() . ')' );
                return;
            }

            PF_MZ_Utils::log( 'STATE response', $state );

            if ( (int) $state['code'] !== 200 || empty( $state['json']['state'] ) && empty( $state['json']['paymentState'] ) ) {
                $order->add_order_note( 'Muzapay: neplatny stav (HTTP ' . $state['code'] . ')' );
                return;
            }

            // API vraci paymentState, fallback na state
            $status = strtoupper( (string) ( $state['json']['paymentState'] ?? $state['json']['state'] ?? '' ) );

            if ( 'PAID' === $status ) {
                PF_MZ_Utils::set_order_state_once( $order, 'paid', 'Muzapay STATE' );
            } elseif ( 'FAILED' === $status ) {
                PF_MZ_Utils::set_order_state_once( $order, 'failed', 'Muzapay STATE' );
            } elseif ( 'CANCELED' === $status ) {
                PF_MZ_Utils::set_order_state_once( $order, 'canceled', 'Muzapay STATE' );
            } else {
                $order->add_order_note( 'Muzapay: stav ' . $status );
            }
        }

        /**
         * Thank-you hook to ensure stav se stahne i po navratu z brany.
         */
        public function poll_on_thankyou( $order_id ): void {
            $order = $order_id ? wc_get_order( $order_id ) : null;
            if ( ! $order || $order->get_payment_method() !== $this->id ) {
                return;
            }
            $payment_id = (string) $order->get_meta( '_pf_mz_payment_id' );
            if ( ! $payment_id ) {
                return;
            }
            $cfg = PF_MZ_Utils::cfg();
            $this->update_order_from_state( $order, $payment_id, $cfg );
        }

        /**
         * Admin akce pro rucni stazeni stavu z Muzapay.
         */
        public function add_admin_action( $actions ) {
            $actions['pf_muzapay_refresh'] = __( 'Muzapay: načíst stav', 'pf-muzapay' );
            return $actions;
        }

        /**
         * Handler rucni admin akce.
         */
        public function handle_admin_action( WC_Order $order ): void {
            if ( $order->get_payment_method() !== $this->id ) {
                return;
            }

            $payment_id = (string) $order->get_meta( '_pf_mz_payment_id' );
            if ( ! $payment_id ) {
                $order->add_order_note( 'Muzapay: rucni nacitani stavu selhalo, chybi paymentId.' );
                return;
            }

            $cfg = PF_MZ_Utils::cfg();
            $this->update_order_from_state( $order, $payment_id, $cfg );
            $order->add_order_note( 'Muzapay: rucni nacitani stavu dokončeno.' );
        }
    }
}

add_filter(
    'woocommerce_payment_gateways',
    function( $gateways ) {
        $gateways[] = 'WC_Gateway_PF_Muzapay';
        return $gateways;
    }
);
