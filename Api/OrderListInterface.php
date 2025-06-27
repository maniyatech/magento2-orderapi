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
     * Get order list with grand total as configured threshold and within defined date range
     *
     * @return \Magento\Sales\Api\Data\OrderInterface[]|array
     */
    public function getOrders(): array;
}
