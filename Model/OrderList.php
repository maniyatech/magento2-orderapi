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
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Address\Config as AddressConfig;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class OrderList implements OrderListInterface
{
    public const XML_PATH_MODULE_ENABLED  = 'orderapi/general/enabled';
    public const XML_PATH_GRAND_TOTAL     = 'orderapi/general/grand_total_threshold';
    public const XML_PATH_EXPORT_DAYS     = 'orderapi/general/export_days';
    public const XML_PATH_FILE_FORMAT     = 'orderapi/general/file_format';

    public const XML_PATH_ENABLE_ORDER_EXPORT = 'orderapi/order_export_settings/enable_export_order';
    public const XML_PATH_SAVE_ORDER_EXPORT_FILE  = 'orderapi/order_export_settings/save_file';
    public const XML_PATH_ORDER_EXPORT_FILE_EMAIL = 'orderapi/order_export_settings/email_attachment';
    public const XML_PATH_ORDER_FIELDS = 'orderapi/order_export_settings/export_fields';

    public const XML_PATH_ENABLE_ORDER_REPORT = 'orderapi/email_settings/enabled_order_report';
    public const XML_PATH_EMAIL_SENDER    = 'orderapi/email_settings/sender';
    public const XML_PATH_EMAIL_TO_NAME   = 'orderapi/email_settings/to_name';
    public const XML_PATH_EMAIL_TO        = 'orderapi/email_settings/to';
    public const XML_PATH_EMAIL_BCC       = 'orderapi/email_settings/bcc';
    public const XML_PATH_EMAIL_TEMPLATE  = 'orderapi/email_settings/template';

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
     * @var GroupRepositoryInterface
     */
    public GroupRepositoryInterface $groupRepository;

    /**
     * @var AddressConfig
     */
    public AddressConfig $addressConfig;

    /**
     * @var TransportBuilder
     */
    private TransportBuilder $transportBuilder;

    /**
     * @var StateInterface
     */
    private StateInterface $inlineTranslation;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManagerInterface;

    /**
     * @var FileDriver
     */
    private FileDriver $fileDriver;

    /**
     * OrderList Constructor.
     *
     * @param CollectionFactory $orderCollectionFactory
     * @param LoggerInterface $logger
     * @param TimezoneInterface $timezone
     * @param ScopeConfigInterface $scopeConfig
     * @param PricingHelper $pricingHelper
     * @param GroupRepositoryInterface $groupRepository
     * @param AddressConfig $addressConfig
     * @param FileDriver $fileDriver
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param StoreManagerInterface $storeManagerInterface
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        LoggerInterface $logger,
        TimezoneInterface $timezone,
        ScopeConfigInterface $scopeConfig,
        PricingHelper $pricingHelper,
        GroupRepositoryInterface $groupRepository,
        AddressConfig $addressConfig,
        FileDriver $fileDriver,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManagerInterface
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
        $this->timezone = $timezone;
        $this->scopeConfig = $scopeConfig;
        $this->pricingHelper = $pricingHelper;
        $this->groupRepository = $groupRepository;
        $this->addressConfig = $addressConfig;
        $this->fileDriver = $fileDriver;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManagerInterface = $storeManagerInterface;
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
     * Retrieve the customer group name (code) by group ID.
     *
     * If the group ID is invalid or not found, returns the group ID as string fallback.
     *
     * @param int|string $groupId
     * @return string
     */
    public function getCustomerGroupName($groupId): string
    {
        try {
            return $this->groupRepository->getById($groupId)->getCode();
        } catch (\Exception $e) {
            return (string) $groupId;
        }
    }

    /**
     * Convert an order address object/array to a plain text HTML-formatted address.
     *
     * Strips HTML tags to return a clean single-line string version of the address.
     *
     * @param \Magento\Sales\Api\Data\OrderAddressInterface|array|null $address
     * @return string
     */
    public function formatAddress($address): string
    {
        if ($address) {
            $renderer = $this->addressConfig->getFormatByCode('html')->getRenderer();
            return strip_tags($renderer->renderArray($address));
        }
        return '';
    }

    /**
     * Retrieve orders based on configured grand total and date range.
     *
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection|null
     */
    public function getOrders(): ?\Magento\Sales\Model\ResourceModel\Order\Collection
    {
        if (!$this->isModuleEnabled()) {
            $this->logger->info('Please enable the module first: ' . __METHOD__);
            return null;
        }

        try {
            $grandTotalThreshold = (float) $this->getConfigValue(self::XML_PATH_GRAND_TOTAL);
            $days = (int) $this->getConfigValue(self::XML_PATH_EXPORT_DAYS);

            $toDate = $this->timezone->date()->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            $fromDate = $this->timezone
                ->date(new \DateTime("-{$days} days"))
                ->setTime(0, 0, 0)
                ->format('Y-m-d H:i:s');
            return $this->getOrderCollection([], $grandTotalThreshold, $fromDate, $toDate);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'Error in %s: %s',
                __METHOD__,
                $e->getMessage()
            ));
        }

        return null;
    }

    /**
     * Retrieve filtered order collection based on provided filters.
     *
     * @param array $orderIds List of order IDs to filter (optional).
     * @param float|null $grandTotalThreshold Minimum grand total filter (optional).
     * @param string|null $fromDate Start date in 'Y-m-d H:i:s' format (optional).
     * @param string|null $toDate End date in 'Y-m-d H:i:s' format (optional).
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function getOrderCollection(
        array $orderIds = [],
        ?float $grandTotalThreshold = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): \Magento\Sales\Model\ResourceModel\Order\Collection {

        $selectedOrderFields = $this->getSelectedOrderFields();
        $defaultOrderFields = $this->getDefaultOrderFields();
        $finalFields = $selectedOrderFields ?: $defaultOrderFields;
        $fieldCodes = array_column($finalFields, 'order_code');

        if (in_array('customer_firstname', $fieldCodes, true) && !in_array('customer_lastname', $fieldCodes, true)) {
            $fieldCodes[] = 'customer_lastname';
        }

        $orders = $this->orderCollectionFactory->create()
            ->addFieldToSelect($fieldCodes);

        if (!empty($orderIds)) {
            $orders->addFieldToFilter('entity_id', ['in' => $orderIds]);
        }

        if ($grandTotalThreshold !== null) {
            $orders->addFieldToFilter('grand_total', ['gt' => $grandTotalThreshold]);
        }

        if ($fromDate !== null && $toDate !== null) {
            $orders->addFieldToFilter('created_at', ['from' => $fromDate, 'to' => $toDate]);
        }

        $orders->setOrder('entity_id', \Magento\Framework\Data\Collection::SORT_ORDER_DESC);
        return $orders;
    }

    /**
     * Get list of header labels for order export.
     *
     * @return array<int, string>
     */
    public function getHeaders(): array
    {
        $selectedOrderFields = $this->getSelectedOrderFields();
        $defaultOrderFields = $this->getDefaultOrderFields();

        $finalFields = $selectedOrderFields ?: $defaultOrderFields;
        $headers = array_values(array_map(
            static fn($field) => $field['order_title'] ?? ucfirst($field['order_code']),
            $finalFields
        ));
        return $headers;
    }

    /**
     * Export selected order field values to the provided sheet and determine min/max order dates.
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Collection $orders
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @return array{minDate: int|null, maxDate: int|null}
     */
    public function getSelectedOrderFieldValues(
        \Magento\Sales\Model\ResourceModel\Order\Collection $orders,
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
    ): array {
        $selectedOrderFields = $this->getSelectedOrderFields();
        $defaultOrderFields = $this->getDefaultOrderFields();
        $finalFields = $selectedOrderFields ?: $defaultOrderFields;

        $fieldCodes = array_column($finalFields, 'order_code');

        $row = 2;
        $minDate = null;
        $maxDate = null;

        foreach ($orders as $order) {
            $rowData = [];

            $createdAt = $order->getCreatedAt();
            if ($createdAt) {
                $timestamp = strtotime($createdAt);

                if ($minDate === null || $timestamp < $minDate) {
                    $minDate = $timestamp;
                }
                if ($maxDate === null || $timestamp > $maxDate) {
                    $maxDate = $timestamp;
                }
            }

            foreach ($fieldCodes as $code) {
                $value = $order->getData($code) ?? '';

                switch ($code) {
                    case 'status':
                        $value = ucfirst((string) $value);
                        break;

                    case 'created_at':
                    case 'updated_at':
                        $value = $value ? date('d-m-Y', strtotime((string) $value)) : '';
                        break;

                    case 'customer_firstname':
                        $firstname = $order->getData('customer_firstname') ?? '';
                        $lastname = $order->getData('customer_lastname') ?? '';
                        $value = trim($firstname . ' ' . $lastname);
                        break;

                    case 'customer_group_id':
                        $groupId = $order->getData('customer_group_id');
                        $value = $this->getCustomerGroupName((int) $groupId);
                        break;

                    case 'shipping_amount':
                    case 'shipping_incl_tax':
                    case 'shipping_tax_amount':
                    case 'subtotal':
                    case 'discount_amount':
                    case 'grand_total':
                    case 'base_grand_total':
                    case 'tax_amount':
                    case 'total_due':
                        $value = $this->getFormattedPrice((float) $value, $order->getOrderCurrencyCode());
                        break;

                    case 'billing_address_id':
                        $value = $this->formatAddress($order->getBillingAddress());
                        break;

                    case 'shipping_address_id':
                        $value = $this->formatAddress($order->getShippingAddress());
                        break;

                    default:
                        $value = (string) $value;
                        break;
                }

                $rowData[] = $value;
            }

            // Write data to the sheet row
            $sheet->fromArray($rowData, null, "A{$row}");
            $row++;
        }

        return ['minDate' => $minDate, 'maxDate' => $maxDate];
    }

    /**
     * Generate a dynamic email subject for the order export report.
     *
     * This method formats the subject line based on the provided date range.
     * If `$fromDate` and `$toDate` are passed, it includes the formatted date range
     * it returns a default subject title.
     *
     * @param string|null $fromDate  Optional start date (in Y-m-d H:i:s or similar format)
     * @param string|null $toDate    Optional end date (in Y-m-d H:i:s or similar format)
     * @return string                Formatted subject line for email
     */
    public function getReportEmailTitle(?string $fromDate = null, ?string $toDate = null)
    {
        $grandTotalThreshold = (float) $this->getConfigValue(self::XML_PATH_GRAND_TOTAL);
        $days = (int) $this->getConfigValue(self::XML_PATH_EXPORT_DAYS);

        if (empty($fromDate) && empty($toDate)) {
            $toDate = $this->timezone->date()->format('Y-m-d H:i:s');
            $fromDate = $this->timezone->date(new \DateTime("-{$days} days"))
                ->setTime(0, 0, 0)
                ->format('Y-m-d H:i:s');
            $fromFormatted = $this->timezone->date(new \DateTime($fromDate))->format('M d, Y');
            $toFormatted = $this->timezone->date(new \DateTime($toDate))->format('M d, Y');
        } else {
            $fromFormatted = (new \DateTime($fromDate))->format('M d, Y');
            $toFormatted   = (new \DateTime($toDate))->format('M d, Y');
        }

        return __('Order Report Between %1 - %2', $fromFormatted, $toFormatted);
    }

   /**
    * Get selected order fields for export from system configuration.
    *
    * The value is retrieved from the path defined in XML_PATH_ORDER_FIELDS.
    * It may be stored as a JSON string or directly as an array.
    *
    * @return array
    */
    public function getSelectedOrderFields(): array
    {
        $selectedOrderFields = $this->scopeConfig->getValue(
            self::XML_PATH_ORDER_FIELDS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        // If already an array, return as-is
        if (is_array($selectedOrderFields)) {
            return $selectedOrderFields;
        }

        // If JSON string, decode and return if valid
        if (is_string($selectedOrderFields)) {
            $decoded = json_decode($selectedOrderFields, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Return empty array if invalid
        return [];
    }

    /**
     * Get default list of order fields used for export when no custom fields are configured.
     *
     * Each field contains:
     * - order_code: internal field key from the sales_order table or derived data
     * - order_title: human-readable label for export headers
     *
     * @return array
     */
    public function getDefaultOrderFields(): array
    {
        $orderCode =
        [
            ['order_code' => 'increment_id', 'order_title' => 'Increment ID'],
            ['order_code' => 'status', 'order_title' => 'Order Status'],
            ['order_code' => 'billing_address_id', 'order_title' => 'Billing Address'],
            ['order_code' => 'shipping_address_id', 'order_title' => 'Shipping Address'],
            ['order_code' => 'created_at', 'order_title' => 'Order Date'],
            ['order_code' => 'customer_firstname', 'order_title' => 'Customer Name'],
            ['order_code' => 'customer_email', 'order_title' => 'Customer Email'],
            ['order_code' => 'shipping_method', 'order_title' => 'Shipping Method'],
            ['order_code' => 'total_qty_ordered', 'order_title' => 'Total Qty Ordered'],
            ['order_code' => 'shipping_amount', 'order_title' => 'Shipping Amount'],
            ['order_code' => 'grand_total', 'order_title' => 'Grand Total'],
        ];

        return $orderCode;
    }

    /**
     * Email Sender Details.
     *
     * @param string $senderCode
     *
     * @return array
     */

    public function getSenderDetails(string $senderCode): array
    {
        $namePath = 'trans_email/ident_' . $senderCode . '/name';
        $emailPath = 'trans_email/ident_' . $senderCode . '/email';
        return [
            'name' => $this->getConfigValue($namePath),
            'email' => $this->getConfigValue($emailPath),
        ];
    }

    /**
     * Send the exported order report via email.
     *
     * @param string $fileFormat
     * @param string $fileName
     * @param string|null $filePath
     * @param string|null $fileContent
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return void
     */
    public function sendOrderReportViaEmail(
        string $fileFormat,
        string $fileName,
        ?string $filePath = null,
        ?string $fileContent = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): void {
        try {
            $senderCode = $this->getConfigValue(self::XML_PATH_EMAIL_SENDER);
            $sender = $this->getSenderDetails($senderCode);
            $to = $this->getConfigValue(self::XML_PATH_EMAIL_TO);
            $receivername = $this->getConfigValue(self::XML_PATH_EMAIL_TO_NAME);
            $bcc = $this->getConfigValue(self::XML_PATH_EMAIL_BCC);
            $template = $this->getConfigValue(self::XML_PATH_EMAIL_TEMPLATE);
            $subject = (string) $this->getReportEmailTitle($fromDate, $toDate);

            $toEmails = array_filter(array_map('trim', explode(',', $to)));
            $bccEmails = array_filter(array_map('trim', explode(',', $bcc)));

            $storeId = $this->storeManagerInterface->getStore()->getId();
            $this->inlineTranslation->suspend();

            $this->transportBuilder
                ->setTemplateIdentifier($template)
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId
                ])
                ->setTemplateVars([
                    'dynamic_subject' => $subject,
                    'receivername' => $receivername
                ])
                ->setFrom($sender)
                ->addTo($toEmails);

            // Attach file
            if ($filePath && $this->fileDriver->isExists($filePath)) {
                $this->transportBuilder->addAttachment(
                    $this->fileDriver->fileGetContents($filePath),
                    $fileName,
                    $fileFormat === 'xlsx'
                        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        : 'text/csv'
                );
            } elseif ($fileContent) {
                $this->transportBuilder->addAttachment(
                    $fileContent,
                    $fileName,
                    $fileFormat === 'xlsx'
                        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        : 'text/csv'
                );
            }

            if (!empty($bccEmails)) {
                $this->transportBuilder->addBcc($bccEmails);
            }

            $transport = $this->transportBuilder->getTransport();
            $transport->sendMessage();
            $this->inlineTranslation->resume();
        } catch (\Exception $e) {
            $this->logger->error(__('Order report email error: %1', $e->getMessage()));
        }
    }
}
