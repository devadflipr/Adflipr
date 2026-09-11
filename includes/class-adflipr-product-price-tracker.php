<?php
defined('ABSPATH') || exit;

/**
 * Detect WooCommerce product price changes via PHP hooks (not WC webhooks)
 * and notify the Adflipr backend for PRICE_DROP / PRICE_INCREASE automation.
 *
 * Old price comes from WooCommerce's in-memory product data before save
 * (`woocommerce_before_product_object_save`), not from Adflipr-owned post meta.
 *
 * @package AdFlipr
 */
if (! class_exists('Adflipr_Product_Price_Tracker')) {

  class Adflipr_Product_Price_Tracker
  {
    /**
     * @var self|null
     */
    public static $_instance = null;

    /**
     * Request-scoped map of product_id => price string before the current save.
     *
     * @var array<int, string>
     */
    private $price_before_save = array();

    public function __construct()
    {
      add_action('woocommerce_before_product_object_save', array($this, 'capture_price_before_save'), 10, 1);
      add_action('woocommerce_product_object_updated_props', array($this, 'maybe_send_price_change'), 10, 2);
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
     * Snapshot the pre-change price while WC still holds it in `$product->get_data()`.
     *
     * @param WC_Product $product Product being saved.
     */
    public function capture_price_before_save($product)
    {
      if (! $product instanceof WC_Product) {
        return;
      }

      $product_id = (int) $product->get_id();
      if ($product_id <= 0) {
        return;
      }

      $data = $product->get_data();
      $this->price_before_save[ $product_id ] = isset($data['price']) ? (string) $data['price'] : '';
    }

    /**
     * Props that mean the active storefront price may have changed.
     * Admin edits usually update `regular_price` / `sale_price`; WC often does
     * not put `price` itself in `$updated_props` (it recalculates `_price` after).
     *
     * @return string[]
     */
    private function price_related_props()
    {
      return array(
        'price',
        'regular_price',
        'sale_price',
        'date_on_sale_from',
        'date_on_sale_to',
      );
    }

    /**
     * Whether `$updated_props` includes anything that can change active price.
     *
     * @param array $updated_props Props WooCommerce marked as changed.
     */
    private function has_price_related_update(array $updated_props)
    {
      return count(array_intersect($this->price_related_props(), $updated_props)) > 0;
    }

    /**
     * When WC reports a price-related prop change, compare old vs new active price
     * and notify Adflipr.
     *
     * @param WC_Product $product       Product after save.
     * @param array      $updated_props Props WooCommerce marked as changed.
     */
    public function maybe_send_price_change($product, $updated_props)
    {
      if (! $product instanceof WC_Product || ! is_array($updated_props)) {
        return;
      }

      // Variable/grouped parents sync price from children — skip (listen on variations).
      if ($product->is_type(array('variable', 'grouped'))) {
        unset($this->price_before_save[ (int) $product->get_id() ]);

        return;
      }

      if (! $this->has_price_related_update($updated_props)) {
        return;
      }

      $product_id = (int) $product->get_id();
      if ($product_id <= 0) {
        return;
      }

      $new_price = (string) $product->get_price();
      $old_price = isset($this->price_before_save[ $product_id ])
        ? (string) $this->price_before_save[ $product_id ]
        : '';

      unset($this->price_before_save[ $product_id ]);

      if (function_exists('adflipr_debug_log')) {
        adflipr_debug_log(
          'product_price_props',
          array(
            'product_id'    => $product_id,
            'updated_props' => array_values(
              array_intersect($this->price_related_props(), $updated_props)
            ),
            'old_price'     => $old_price,
            'new_price'     => $new_price,
          )
        );
      }

      // First save / no prior price — nothing to compare.
      if ($old_price === '') {
        return;
      }

      if (! is_numeric($old_price) || ! is_numeric($new_price)) {
        return;
      }

      $old = (float) $old_price;
      $new = (float) $new_price;

      if ($old === $new) {
        return;
      }

      // Action must match WooCommerceService cases: PRICE_DROP / PRICE_INCREASE.
      $action = $new < $old ? 'price_drop' : 'price_increase';

      $product_data = null;
      if (isset(AdFlipr_Core()->data) && is_object(AdFlipr_Core()->data)) {
        $product_data = AdFlipr_Core()->data->get_single_product_data($product);
      }

      $payload = array(
        'action'     => $action,
        'product_id' => $product_id,
        'old_price'  => $old_price,
        'new_price'  => $new_price,
        'currency'   => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
      );

      if (is_array($product_data)) {
        $payload['product'] = $product_data;
      }

      if (function_exists('adflipr_debug_log')) {
        adflipr_debug_log(
          'product_price_changed',
          array(
            'product_id' => $product_id,
            'old_price'  => $old_price,
            'new_price'  => $new_price,
            'action'     => $action,
          )
        );
      }

      if (! isset(AdFlipr_Core()->data) || ! is_object(AdFlipr_Core()->data)) {
        return;
      }

      AdFlipr_Core()->data->send_data_on_webhook($payload);
    }
  }

  if (class_exists('Adflipr_Product_Price_Tracker')) {
    AdFlipr_Core::register('product_price_tracker', 'Adflipr_Product_Price_Tracker');
  }
}
