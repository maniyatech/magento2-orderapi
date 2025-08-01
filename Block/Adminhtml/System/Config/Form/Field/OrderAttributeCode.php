<?php
/**
 * ManiyaTech
 *
 * @author        Milan Maniya
 * @package       ManiyaTech_OrderApi
 */

namespace ManiyaTech\OrderApi\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\View\Element\Html\Select;
use Magento\Framework\Data\OptionSourceInterface;

class OrderAttributeCode extends Select
{
    /**
     * Render the HTML for the select element.
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getOrderAttributes());
        }
        
        $this->setName($this->getInputName());
        $this->setId($this->getInputId());
        $this->setClass('select admin__control-select');
        $this->setTitle(__('Select Order Attribute Code'));
        return parent::_toHtml();
    }

    /**
     * Get list of available order attributes to be used in the select field.
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    protected function getOrderAttributes(): array
    {
        return [
            ['value' => 'increment_id', 'label' => __('Increment ID')],
            ['value' => 'status', 'label' => __('Order Status')],
            ['value' => 'state', 'label' => __('Order State')],
            ['value' => 'created_at', 'label' => __('Order Date')],
            ['value' => 'updated_at', 'label' => __('Updated At')],
            ['value' => 'customer_firstname', 'label' => __('Customer Name')],
            ['value' => 'customer_email', 'label' => __('Customer Email')],
            ['value' => 'customer_group_id', 'label' => __('Customer Group')],
            ['value' => 'coupon_code', 'label' => __('Coupon Code')],
            ['value' => 'shipping_method', 'label' => __('Shipping Method')],
            ['value' => 'shipping_description', 'label' => __('Shipping Description')],
            ['value' => 'shipping_amount', 'label' => __('Shipping Amount')],
            ['value' => 'shipping_incl_tax', 'label' => __('Shipping Incl. Tax')],
            ['value' => 'shipping_tax_amount', 'label' => __('Shipping Tax Amount')],
            ['value' => 'subtotal', 'label' => __('Subtotal')],
            ['value' => 'discount_amount', 'label' => __('Discount Amount')],
            ['value' => 'grand_total', 'label' => __('Grand Total')],
            ['value' => 'base_grand_total', 'label' => __('Base Grand Total')],
            ['value' => 'tax_amount', 'label' => __('Tax Amount')],
            ['value' => 'total_due', 'label' => __('Total Due')],
            ['value' => 'total_item_count', 'label' => __('Total Item Count')],
            ['value' => 'total_qty_ordered', 'label' => __('Total Qty Ordered')],
            ['value' => 'billing_address_id', 'label' => __('Billing Address')],
            ['value' => 'shipping_address_id', 'label' => __('Shipping Address')],
        ];
    }
}
