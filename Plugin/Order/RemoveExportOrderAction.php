<?php
/**
 * ManiyaTech
 *
 * @author        Milan Maniya
 * @package       ManiyaTech_OrderApi
 */

declare(strict_types=1);

namespace ManiyaTech\OrderApi\Plugin\Order;

use Magento\Ui\Component\MassAction;
use ManiyaTech\OrderApi\Model\OrderList;

class RemoveExportOrderAction
{
    /**
     * @var DeleteOrders
     */
    protected OrderList $orderList;

    /**
     * RemoveExportOrderAction constructor.
     *
     * @param OrderList $orderList
     */
    public function __construct(
        OrderList $orderList
    ) {
        $this->orderList = $orderList;
    }

    /**
     * Remove ExportOrder Action in Order Grid
     *
     * @param MassAction $object
     * @param array $result
     * @return mixed
     */

    public function afterGetChildComponents(MassAction $object, $result)
    {
        if (!isset($result['order_export'])) {
            return $result;
        }

        if (!$this->orderList->getConfigValue(OrderList::XML_PATH_ENABLE_ORDER_EXPORT)) {
            unset($result['order_export']);
        }

        return $result;
    }
}
