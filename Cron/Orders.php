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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use ManiyaTech\OrderApi\Model\OrderList;
use Psr\Log\LoggerInterface;

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
                $this->logger->info('Please enable the module first: ' . __METHOD__);
                return;
            }

            $orders = $this->orderList->getOrders();
            $days = $this->orderList->getConfigValue(OrderList::XML_PATH_EXPORT_DAYS);
            $fileFormat = $this->orderList->getConfigValue(OrderList::XML_PATH_FILE_FORMAT);

            if (empty($orders)) {
                $this->logger->info(__('No orders to export in last %1 days.', $days));
                return;
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $headers = $this->orderList->getHeaders();

            $sheet->fromArray($headers, null, 'A1');
            if ($fileFormat === 'xlsx') {
                $highestColumn = $sheet->getHighestColumn();
                $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);

                foreach ($headers as $index => $header) {
                    $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
                    $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
                }
            }
            $this->orderList->getSelectedOrderFieldValues($orders, $sheet);

            $exportDir = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/exportorder';
            if (!$this->fileDriver->isDirectory($exportDir)) {
                $this->fileDriver->createDirectory($exportDir, 0775);
            }

            $fileName = 'order_export_' . date('d-m-Y_h:i_A') . '.' . $fileFormat;
            $filePath = $exportDir . '/' . $fileName;
            if ($fileFormat === 'xlsx') {
                (new Xlsx($spreadsheet))->save($filePath);
            } else {
                $writer = new Csv($spreadsheet);
                $writer->setDelimiter(',');
                $writer->setEnclosure('"');
                $writer->setLineEnding("\r\n");
                $writer->save($filePath);
            }

            if ($this->orderList->getConfigValue(OrderList::XML_PATH_ENABLE_ORDER_REPORT)) {
                $this->orderList->sendOrderReportViaEmail($fileFormat, $fileName, $filePath);
            }
            $this->logger->info(__('Order export completed: %1', $filePath));
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
