<?php
defined('ABSPATH') || exit;

/**
 * Detect WooCommerce product SKU / stock-status changes via PHP hooks (not WC webhooks)
 * and notify the Adflipr backend for SKU_CHANGE handling.
 *
 * Fires when SKU changes and/or stock status changes (instock|outofstock|onbackorder),
 * including stock-status-only edits that leave the SKU unchanged.
 *
 * Old values come from WooCommerce's in-memory product data before save
 * (`woocommerce_before_product_object_save`), not from Adflipr-owned post meta.
 *
 * @package AdFlipr
 */
if (! class_exists('Adflipr_Product_Sku_Tracker')) {

  class Adflipr_Product_Sku_Tracker
  {
    /**
     * @var self|null
     */
    public static $_instance = null;

    /**
     * Request-scoped map of product_id => [sku, stock_status] before the current save.
     *
     * @var array<int, array{sku: string, stock_status: string}>
     */
    private $before_save = array();

    public function __construct()
    {
      add_action('woocommerce_before_product_object_save', array($this, 'capture_before_save'), 10, 1);
      add_action('woocommerce_product_object_updated_props', array($this, 'maybe_send_sku_change'), 10, 2);
    }

    /**
     * @return self|null
     */
    public static function get_instance()
    {
      if (null === self::$_instance) {
        self::$_instance = new self();
      }

      return self::$_instance;
    }

    /**
     * Snapshot SKU + stock status while WC still holds them in `$product->get_data()`.
     *
     * @param WC_Product $product Product being saved.
     */
    public function capture_before_save($product)
    {
      if (! $product instanceof WC_Product) {
        return;
      }

      $product_id = (int) $product->get_id();
      if ($product_id <= 0) {
        return;
      }

      $data = $product->get_data();
      $this->before_save[ $product_id ] = array(
        'sku'          => isset($data['sku']) ? (string) $data['sku'] : '',
        'stock_status' => isset($data['stock_status']) ? (string) $data['stock_status'] : '',
      );
    }

    /**
     * Props that can change SKU or effective stock status.
     *
     * @return string[]
     */
    private function tracked_props()
    {
      return array(
        'sku',
        'stock_status',
        'stock_quantity',
        'manage_stock',
        'backorders',
      );
    }

    /**
     * @param array $updated_props Props WooCommerce marked as changed.
     */
    private function has_tracked_update(array $updated_props)
    {
      return count(array_intersect($this->tracked_props(), $updated_props)) > 0;
    }

    /**
     * When WC reports a SKU or stock-status-related prop change, notify Adflipr.
     *
     * @param WC_Product $product       Product after save.
     * @param array      $updated_props Props WooCommerce marked as changed.
     */
    public function maybe_send_sku_change($product, $updated_props)
    {
      if (! $product instanceof WC_Product || ! is_array($updated_props)) {
        return;
      }

      // Variable/grouped parents sync from children — skip (listen on variations / leaf products).
      if ($product->is_type(array('variable', 'grouped'))) {
        unset($this->before_save[ (int) $product->get_id() ]);

        return;
      }

      if (! $this->has_tracked_update($updated_props)) {
        return;
      }

      $product_id = (int) $product->get_id();
      if ($product_id <= 0) {
        return;
      }

      $before = isset($this->before_save[ $product_id ]) && is_array($this->before_save[ $product_id ])
        ? $this->before_save[ $product_id ]
        : array('sku' => '', 'stock_status' => '');

      unset($this->before_save[ $product_id ]);

      $old_sku          = trim((string) ($before['sku'] ?? ''));
      $new_sku          = trim((string) $product->get_sku());
      $old_stock_status = (string) ($before['stock_status'] ?? '');
      $new_stock_status = (string) $product->get_stock_status();

      $sku_changed          = ($old_sku !== $new_sku);
      $stock_status_changed = ($old_stock_status !== '' && $old_stock_status !== $new_stock_status);

      if (function_exists('adflipr_debug_log')) {
        adflipr_debug_log(
          'product_sku_props',
          array(
            'product_id'       => $product_id,
            'updated_props'    => array_values(array_intersect($this->tracked_props(), $updated_props)),
            'old_sku'          => $old_sku,
            'new_sku'          => $new_sku,
            'old_stock_status' => $old_stock_status,
            'new_stock_status' => $new_stock_status,
          )
        );
      }

      if (! $sku_changed && ! $stock_status_changed) {
        return;
      }

      // Action must match WooCommerceService cases: SKU_CHANGE.
      $product_data = null;
      if (isset(AdFlipr_Core()->data) && is_object(AdFlipr_Core()->data)) {
        $product_data = AdFlipr_Core()->data->get_single_product_data($product);
      }

      $payload = array(
        'action'       => 'sku_change',
        'product_id'   => $product_id,
        'old_sku'      => $old_sku,
        'new_sku'      => $new_sku,
        'stock_status' => $new_stock_status,
      );

      if (is_array($product_data)) {
        $payload['product'] = $product_data;
      }

      if (function_exists('adflipr_debug_log')) {
        adflipr_debug_log(
          'product_sku_changed',
          array(
            'product_id'   => $product_id,
            'old_sku'      => $old_sku,
            'new_sku'      => $new_sku,
            'stock_status' => $new_stock_status,
            'action'       => 'sku_change',
          )
        );
      }

      if (! isset(AdFlipr_Core()->data) || ! is_object(AdFlipr_Core()->data)) {
        return;
      }

      AdFlipr_Core()->data->send_data_on_webhook($payload);
    }
  }

  if (class_exists('Adflipr_Product_Sku_Tracker')) {
    AdFlipr_Core::register('product_sku_tracker', 'Adflipr_Product_Sku_Tracker');
  }
}
