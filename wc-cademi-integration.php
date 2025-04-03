<?php
/*
Plugin Name: WC Educpay Integration
 * Plugin URI: https://ab.rio.br/
 * Description: Plugin de Ensalamento WooCommerce (Educpay).
 * Version: 1.2
 * Author: Agência AB Rio
 * Author URI: https://wa.me/5521991242544
 * Requires at least: 6.4
 * Requires PHP: 7.4
 *
 * @package Edcpay
*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin path
define('WC_CADEMI_INTEGRATION_PATH', plugin_dir_path(__FILE__));

// Include the main class
require_once WC_CADEMI_INTEGRATION_PATH . 'includes/class-wc-cademi-integration.php';

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('WCCademiIntegration', 'activate'));
register_deactivation_hook(__FILE__, array('WCCademiIntegration', 'deactivate'));

// Initialize the plugin
add_action('plugins_loaded', array('WCCademiIntegration', 'init'));
?>
