<?php
/**
 * ManiyaTech
 *
 * @author        Milan Maniya
 * @package       ManiyaTech_OrderApi
 */

declare(strict_types=1);

namespace ManiyaTech\OrderApi\Model;

use ManiyaTech\OrderApi\Api\OrderListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class OrderList implements OrderListInterface
{
    public const XML_PATH_MODULE_ENABLED  = 'orderapi/general/enabled';
    public const XML_PATH_GRAND_TOTAL     = 'orderapi/general/grand_total_threshold';
    public const XML_PATH_EXPORT_DAYS     = 'orderapi/general/export_days';
    public const XML_PATH_ENABLE_ORDER_REPORT = 'orderapi/general/enabled_order_report';
    public const XML_PATH_EMAIL_SENDER    = 'orderapi/general/sender';
    public const XML_PATH_EMAIL_TO_NAME   = 'orderapi/general/to_name';
    public const XML_PATH_EMAIL_TO        = 'orderapi/general/to';
    public const XML_PATH_EMAIL_BCC       = 'orderapi/general/bcc';
    public const XML_PATH_EMAIL_TEMPLATE  = 'orderapi/general/template';

    /**
     * @var CollectionFactory
     */
    public CollectionFactory $orderCollectionFactory;

    /**
     * @var LoggerInterface
     */
    public LoggerInterface $logger;

    /**
     * @var TimezoneInterface
     */
    public TimezoneInterface $timezone;

    /**
     * @var ScopeConfigInterface
     */
    public ScopeConfigInterface $scopeConfig;

    /**
     * @var PricingHelper
     */
    public PricingHelper $pricingHelper;

    /**
     * OrderList Constructor.
     *
     * @param CollectionFactory $orderCollectionFactory
     * @param LoggerInterface $logger
     * @param TimezoneInterface $timezone
     * @param ScopeConfigInterface $scopeConfig
     * @param PricingHelper $pricingHelper
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        LoggerInterface $logger,
        TimezoneInterface $timezone,
        ScopeConfigInterface $scopeConfig,
        PricingHelper $pricingHelper
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
        $this->timezone = $timezone;
        $this->scopeConfig = $scopeConfig;
        $this->pricingHelper = $pricingHelper;
    }

    /**
     * Check if module is enabled from config
     */
    public function isModuleEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_PATH_MODULE_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get configuration value by path.
     *
     * @param string $path Config path from system configuration.
     * @return string
     */
    public function getConfigValue(string $path): string
    {
        return (string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Format price with currency symbol.
     *
     * @param float $price Price value to format.
     * @return string
     */
    public function getFormattedPrice(float $price): string
    {
        return $this->pricingHelper->currency($price, true, false);
    }

    /**
     * Retrieve list of filtered orders
     */
    public function getOrders(): array
    {
        $orders = [];

        if (!$this->isModuleEnabled()) {
            return $orders;
        }

        try {
            $grandTotalThreshold = (float) $this->getConfigValue(self::XML_PATH_GRAND_TOTAL);
            $days                = (int) $this->getConfigValue(self::XML_PATH_EXPORT_DAYS);

            $toDate   = $this->timezone->date()->format('Y-m-d H:i:s');
            $fromDate = $this->timezone->date(new \DateTime("-{$days} days"))
                ->setTime(0, 0, 0)
                ->format('Y-m-d H:i:s');

            $collection = $this->orderCollectionFactory->create()
                ->addFieldToSelect([
                    'entity_id',
                    'increment_id',
                    'grand_total',
                    'customer_firstname',
                    'customer_lastname',
                    'shipping_method',
                    'shipping_description',
                    'shipping_amount',
                    'shipping_incl_tax',
                    'created_at',
                    'status'
                ])
                ->addFieldToFilter('grand_total', ['gt' => $grandTotalThreshold])
                ->addFieldToFilter('created_at', ['from' => $fromDate, 'to' => $toDate])
                ->setOrder('entity_id', 'DESC');

            foreach ($collection as $order) {
                $billingAddress = $order->getBillingAddress();
                $payment        = $order->getPayment();

                $paymentTitle = null;
                if ($payment) {
                    $methodInstance = $payment->getMethodInstance();
                    $paymentTitle   = $methodInstance->getTitle() ?: $payment->getMethod();
                }

                $formattedDate = $this->timezone
                    ->date(new \DateTime($order->getCreatedAt()))
                    ->format('M d, Y, g:i:s A');

                $orders[] = [
                    'increment_id'         => $order->getIncrementId(),
                    'billing_name'         => $billingAddress
                        ? $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname()
                        : null,
                    'grand_total'          => $this->getFormattedPrice((float) $order->getGrandTotal()),
                    'order_status'         => $order->getStatus(),
                    'order_date'           => $formattedDate,
                    'payment_method'       => $payment?->getMethod(),
                    'payment_title'        => $paymentTitle,
                    'shipping_method'      => $order->getShippingMethod(),
                    'shipping_description' => $order->getShippingDescription(),
                    'shipping_amount'      => $this->getFormattedPrice((float) $order->getShippingAmount()),
                    'shipping_incl_tax'    => $this->getFormattedPrice((float) $order->getShippingInclTax()),
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error in ' . __METHOD__ . ': ' . $e->getMessage());
        }

        return $orders;
    }

    /**
     * Generate dynamic email subject for the order report.
     *
     * @return string
     */
    public function getReportEmailTitle()
    {
        $grandTotalThreshold = (float) $this->getConfigValue(self::XML_PATH_GRAND_TOTAL);
        $days = (int) $this->getConfigValue(self::XML_PATH_EXPORT_DAYS);

        $toDate = $this->timezone->date()->format('Y-m-d H:i:s');
        $fromDate = $this->timezone->date(new \DateTime("-{$days} days"))
            ->setTime(0, 0, 0)
            ->format('Y-m-d H:i:s');

        $fromFormatted = $this->timezone->date(new \DateTime($fromDate))->format('M d, Y');
        $toFormatted = $this->timezone->date(new \DateTime($toDate))->format('M d, Y');

        return __('Order Report Between %1 - %2', $fromFormatted, $toFormatted);
    }
}
