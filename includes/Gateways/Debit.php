<?php
namespace WeDevs\WePOS\Gateways;

use Automattic\WooCommerce\Enums\OrderInternalStatus;

/**
 * Debit card gateway payment for POS
 */
class Debit extends \WC_Payment_Gateway
{

    protected $instructions;
    protected $enable_for_methods;
    protected $enable_for_virtual;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        // Setup general properties.
        $this->setup_properties();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Get settings.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->enable_for_methods = $this->get_option('enable_for_methods', array());
        $this->enable_for_virtual = $this->get_option('enable_for_virtual', 'yes') === 'yes';

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties()
    {
        $this->id = 'wepos_debit';
        $this->icon = apply_filters('wepos_debit_icon', '');
        $this->method_title = __('Débito', 'wepos');
        $this->method_description = __('Pago con tarjeta de débito', 'wepos');
        $this->has_fields = false;
        $this->supports = array(
            'refunds'
        );
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'wepos'),
                'label' => __('Enable debit gateway', 'wepos'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'wepos'),
                'type' => 'text',
                'description' => __('Payment method description that the merchant sees in POS checkout', 'wepos'),
                'default' => __('Débito', 'wepos'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'wepos'),
                'type' => 'textarea',
                'description' => __('Payment method description that merchant sees in POS checkout page', 'wepos'),
                'default' => __('Pago con tarjeta de débito', 'wepos'),
                'desc_tip' => true,
            )
        );
    }

    /**
     * Check If The Gateway Is Available For Use.
     *
     * @return bool
     */
    public function is_available()
    {
        $order = null;
        $needs_shipping = false;

        // Test if shipping is needed first.
        if (is_page(wc_get_page_id('checkout'))) {
            return true;
        }

        return parent::is_available() && wepos_is_frontend();
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     *
     * @return array
     *
     * @throws \WC_Data_Exception
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // Mark as processing or on-hold (payment won't be taken until delivery).
        $order->payment_complete();

        $voucher = $order->get_meta('_wepos_card_voucher', true);

        $order->update_status('completed', sprintf(
            __('Pago recibido vía Débito. Voucher: %s', 'wepos'),
            $voucher ? $voucher : __('(sin voucher)', 'wepos')
        ));

        $order->save();

        // Return thankyou redirect.
        return array(
            'result' => 'success',
        );
    }

    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
     *
     * @param  int        $order_id Order ID.
     * @param  float|null $amount Refund amount.
     * @param  string     $reason Refund reason.
     * @return bool|\WP_Error True or false based on success, or a WP_Error object.
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);

        if (!$this->can_refund_order($order)) {
            return new \WP_Error('error', __('Refund failed.', 'wepos'));
        }

        $order->add_order_note(
            /* translators: 1: Refund amount, 2: Refund reason */
            sprintf(__('Refunded %1$s - Reason: %2$s', 'wepos'), $amount, $reason)
        );

        $order->update_status(OrderInternalStatus::REFUNDED);
        $order->save();

        return true;
    }
}
