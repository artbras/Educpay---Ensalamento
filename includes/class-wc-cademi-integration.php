<?php

class WCCademiIntegration {
    private static $instance = null;

    public static function init() {
        if (null == self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Load admin settings
        if (is_admin()) {
            require_once WC_CADEMI_INTEGRATION_PATH . 'admin/class-wc-cademi-integration-admin.php';
            new WCCademiIntegrationAdmin();
        }

        // Hook into WooCommerce order status change
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
    }

    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_cademi_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            order_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL,
            response text NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function deactivate() {
        // No actions on deactivate
    }

    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        // Enviar dados apenas se o status do pedido for "completed", "cancelled" ou "refunded"
        if (!in_array($new_status, ['completed', 'cancelled', 'refunded'])) {
            return;
        }

        $url = get_option('wc_cademi_integration_url');
        $token = get_option('wc_cademi_integration_token');

        if (!$url || !$token) {
            return;
        }

        // Renomear status do pedido conforme solicitado
        $status_map = array(
            'completed' => 'aprovado',
            'cancelled' => 'cancelado',
            'refunded' => 'disputa',
        );

        $status_para_envio = isset($status_map[$new_status]) ? $status_map[$new_status] : $new_status;

        $items = $order->get_items();
        if (empty($items)) {
            return; // No items in order, nothing to do
        }

        $first_item = reset($items);

        $data = array(
            'token' => $token,
            'codigo' => $order_id,
            'status' => $status_para_envio,
            'produto_id' => $first_item->get_product_id(),
            'cliente_email' => $order->get_billing_email(),
            'cliente_nome' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'cliente_doc' => $order->get_meta('_billing_cpf'), // Assuming the CPF is stored in this meta field
            'cliente_celular' => $order->get_billing_phone(),
        );

        try {
            $response = $this->send_cademi_data($url, $data);
            $this->log_to_db($order_id, $new_status, $response);
        } catch (Exception $e) {
            $this->log_to_db($order_id, $new_status, 'Erro ao enviar dados para ensalamento: ' . $e->getMessage());
            error_log('Erro ao enviar dados para ensalamento: ' . $e->getMessage());
        }
    }

    private function send_cademi_data($url, $data) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        switch ($httpcode) {
            case 200:
                return $response; // Return the raw response for displaying
            case 409:
                $json = json_decode($response);
                throw new \Exception($json->msg);
            default:
                throw new \Exception("Erro - " . $httpcode);
        }
    }

    private function log_to_db($order_id, $status, $response) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_cademi_logs';
        $wpdb->insert(
            $table_name,
            array(
                'time' => current_time('mysql'),
                'order_id' => $order_id,
                'status' => $status,
                'response' => $response,
            )
        );
    }
}
?>
