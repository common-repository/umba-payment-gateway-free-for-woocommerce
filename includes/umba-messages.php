<?php

/**
 * 
 * WP Admin messages
 * 
 * @since      1.0.0
 * @package    umba-payment-gateway-free-for-woocommerce
 * @subpackage umba-payment-gateway-free-for-woocommerce/includes
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'UMBA_Message' ) ){

    class UMBA_Message {

        private $message;
        private $type;
        private $isdismissible;

        public function __construct( $message, $type, $isdismissible ) {

            $this->message         = $message;
            $this->type            = $type;
            $this->isdismissible   = $isdismissible;
            add_action( 'admin_notices', array( $this, 'show_message' ) );

        }

        public function show_message(){
            if(!empty($this->message) && !empty($this->type)){
                $dismissible = ($this->isdismissible == true) ? 'is-dismissible' : '';
                ?>
                <div class="umba-notice notice notice-<?php echo $this->type; ?> <?php echo $dismissible; ?>" data-notice="umba_notice">
                    <p><?php echo $this->message; ?></p>
                </div>
            <?php
            }
        }

    }

}