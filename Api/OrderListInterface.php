<?php
/**
 * ManiyaTech
 *
 * @author        Milan Maniya
 * @package       ManiyaTech_OrderApi
 */

namespace ManiyaTech\OrderApi\Api;

interface OrderListInterface
{
    /**
     * Get order list with grand total above configured threshold and within a defined date range.
     *
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection|null
     */
    public function getOrders(): ?\Magento\Sales\Model\ResourceModel\Order\Collection;
}
