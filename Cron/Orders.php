<?php
/**
 * ManiyaTech
 *
 * @author        Milan Maniya
 * @package       ManiyaTech_OrderApi
 */

declare(strict_types=1);

namespace ManiyaTech\OrderApi\Cron;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Filesystem\Glob;
use Psr\Log\LoggerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use ManiyaTech\OrderApi\Model\OrderList;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;

class Orders
{
    /**
     * @var OrderList
     */
    private OrderList $orderList;

    /**
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var FileDriver
     */
    private FileDriver $fileDriver;

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
     * Orders constructor.
     *
     * @param OrderList $orderList
     * @param DirectoryList $directoryList
     * @param LoggerInterface $logger
     * @param FileDriver $fileDriver
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param StoreManagerInterface $storeManagerInterface
     */
    public function __construct(
        OrderList $orderList,
        DirectoryList $directoryList,
        LoggerInterface $logger,
        FileDriver $fileDriver,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManagerInterface
    ) {
        $this->orderList = $orderList;
        $this->directoryList = $directoryList;
        $this->logger = $logger;
        $this->fileDriver = $fileDriver;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManagerInterface = $storeManagerInterface;
    }

    /**
     * Export orders to Excel file.
     *
     * @return void
     */
    public function exportOrders(): void
    {
        try {
            if (!$this->orderList->isModuleEnabled()) {
                return;
            }

            $orders = $this->orderList->getOrders();
            $days = $this->orderList->getConfigValue(OrderList::XML_PATH_EXPORT_DAYS);

            if (empty($orders)) {
                $this->logger->info(__('No orders to export in last %1 days.', $days));
                return;
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $headers = [
                'Increment ID', 'Billing Name', 'Grand Total', 'Status', 'Order Date',
                'Payment Title', 'Shipping Method', 'Shipping Description',
                'Shipping Amount', 'Shipping Incl Tax'
            ];

            $sheet->fromArray($headers, null, 'A1');
            $highestColumn = $sheet->getHighestColumn();
            $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);

            foreach ($headers as $index => $header) {
                $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
            }

            $row = 2;
            foreach ($orders as $order) {
                $sheet->fromArray([
                    $order['increment_id'] ?? '',
                    $order['billing_name'] ?? '',
                    $order['grand_total'] ?? '',
                    $order['order_status'] ?? '',
                    $order['order_date'] ?? '',
                    $order['payment_title'] ?? '',
                    $order['shipping_method'] ?? '',
                    $order['shipping_description'] ?? '',
                    $order['shipping_amount'] ?? '',
                    $order['shipping_incl_tax'] ?? ''
                ], null, "A{$row}");
                $row++;
            }

            $exportDir = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/exportorder';
            if (!$this->fileDriver->isDirectory($exportDir)) {
                $this->fileDriver->createDirectory($exportDir, 0775);
            }

            $fileName = $exportDir . '/order_export_' . date('d-m-Y_h:i_A') . '.xlsx';
            (new Xlsx($spreadsheet))->save($fileName);

            if ($this->orderList->getConfigValue(OrderList::XML_PATH_ENABLE_ORDER_REPORT)) {
                $senderCode = $this->orderList->getConfigValue(OrderList::XML_PATH_EMAIL_SENDER);
                $sender = $this->getSenderDetails($senderCode);
                $to = $this->orderList->getConfigValue(OrderList::XML_PATH_EMAIL_TO);
                $receivername = $this->orderList->getConfigValue(OrderList::XML_PATH_EMAIL_TO_NAME);
                $bcc = $this->orderList->getConfigValue(OrderList::XML_PATH_EMAIL_BCC);
                $template = $this->orderList->getConfigValue(OrderList::XML_PATH_EMAIL_TEMPLATE);
                $subject = (string) $this->orderList->getReportEmailTitle();

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
                    ->addTo($toEmails)
                    ->addAttachment(
                        $this->fileDriver->fileGetContents($fileName),
                        // phpcs:ignore Magento2.Functions.DiscouragedFunction
                        basename($fileName),
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    );

                if (!empty($bccEmails)) {
                    $this->transportBuilder->addBcc($bccEmails);
                }

                $transport = $this->transportBuilder->getTransport();
                $transport->sendMessage();
                $this->inlineTranslation->resume();
            }

            $this->logger->info(__('Order export completed: %1', $fileName));
        } catch (\Throwable $e) {
            $this->logger->error(__('ExportOrders Cron Error: %1', $e->getMessage()));
        }
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
            'name' => $this->orderList->getConfigValue($namePath),
            'email' => $this->orderList->getConfigValue($emailPath),
        ];
    }

    /**
     * Delete older export files, keep only latest 5.
     *
     * @return void
     */
    public function deleteOldFiles(): void
    {
        try {
            if (!$this->orderList->isModuleEnabled()) {
                return;
            }

            $directory = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/exportorder';
            $pattern = $directory . '/order_export_*.xlsx';
            $files = Glob::glob($pattern);

            usort($files, fn($a, $b) => $this->fileDriver->stat($b)['mtime'] <=> $this->fileDriver->stat($a)['mtime']);

            foreach (array_slice($files, 5) as $file) {
                if ($this->fileDriver->isFile($file)) {
                    $this->fileDriver->deleteFile($file);
                    // phpcs:ignore Magento2.Functions.DiscouragedFunction
                    $this->logger->info(__('Deleted old export file: %1', basename($file)));
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error(__('DeleteOldFiles Cron Error: %1', $e->getMessage()));
        }
    }
}
