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
     * Orders constructor.
     *
     * @param OrderList $orderList
     * @param DirectoryList $directoryList
     * @param LoggerInterface $logger
     * @param FileDriver $fileDriver
     */
    public function __construct(
        OrderList $orderList,
        DirectoryList $directoryList,
        LoggerInterface $logger,
        FileDriver $fileDriver
    ) {
        $this->orderList = $orderList;
        $this->directoryList = $directoryList;
        $this->logger = $logger;
        $this->fileDriver = $fileDriver;
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

            // Set headers
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

            // Fill order data
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
            $writer = new Xlsx($spreadsheet);
            $writer->save($fileName);

            $this->logger->info(__('Order export completed: %1', $fileName));
        } catch (\Throwable $e) {
            $this->logger->error(__('ExportOrders Cron Error: %1', $e->getMessage()));
        }
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

            usort($files, function ($a, $b) {
                return $this->fileDriver->stat($b)['mtime'] <=> $this->fileDriver->stat($a)['mtime'];
            });

            $oldFiles = array_slice($files, 5); // Keep 5 latest

            foreach ($oldFiles as $file) {
                if ($this->fileDriver->isFile($file)) {
                    $this->fileDriver->deleteFile($file);
                    $filename = (new \SplFileInfo($file))->getFilename();
                    $this->logger->info(__('Deleted old export file: %1', $filename));
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error(__('DeleteOldFiles Cron Error: %1', $e->getMessage()));
        }
    }
}
