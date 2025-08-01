<?php
/**
 * ManiyaTech
 *
 * @author        Milan Maniya
 * @package       ManiyaTech_OrderApi
 */

namespace ManiyaTech\OrderApi\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use ManiyaTech\OrderApi\Model\OrderList;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

class MassExport extends AbstractMassAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ManiyaTech_OrderApi::mass_export';

    /**
     * @var FileFactory
     */
    private FileFactory $fileFactory;

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
     * MassExport constructor.
     *
     * @param Action\Context $context
     * @param CollectionFactory $collectionFactory
     * @param FileFactory $fileFactory
     * @param DirectoryList $directoryList
     * @param OrderList $orderList
     * @param LoggerInterface $logger
     * @param FileDriver $fileDriver
     * @param Filter $filter
     */
    public function __construct(
        Action\Context $context,
        CollectionFactory $collectionFactory,
        FileFactory $fileFactory,
        DirectoryList $directoryList,
        OrderList $orderList,
        LoggerInterface $logger,
        FileDriver $fileDriver,
        Filter $filter
    ) {
        parent::__construct($context, $filter);
        $this->collectionFactory = $collectionFactory;
        $this->fileFactory = $fileFactory;
        $this->directoryList = $directoryList;
        $this->orderList = $orderList;
        $this->logger = $logger;
        $this->fileDriver = $fileDriver;
    }

    /**
     * Check permission via ACL
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }

    /**
     * Perform export action on selected orders.
     *
     * @param AbstractCollection $collection
     * @return \Magento\Framework\Controller\ResultInterface
     */
    protected function massAction(AbstractCollection $collection)
    {
        try {
            $orderIds = array_map(
                static fn($order) => $order->getId(),
                iterator_to_array($collection)
            );

            if (empty($orderIds)) {
                $this->messageManager->addErrorMessage(__('No orders selected.'));
                return $this->_redirect('sales/order/index');
            }

            if (!$this->orderList->getConfigValue(OrderList::XML_PATH_ENABLE_ORDER_EXPORT)) {
                $this->messageManager->addErrorMessage(__('Please enable Order Export Action first.'));
                return $this->_redirect('sales/order/index');
            }

            $fileFormat = $this->orderList->getConfigValue(OrderList::XML_PATH_FILE_FORMAT);
            $saveToFile = $this->orderList->getConfigValue(OrderList::XML_PATH_SAVE_ORDER_EXPORT_FILE);
            $orders     = $this->orderList->getOrderCollection($orderIds, null, null, null);
            $headers    = $this->orderList->getHeaders();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($headers, null, 'A1');

            if ($fileFormat === 'xlsx') {
                $highestColumn = $sheet->getHighestColumn();
                $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);

                foreach ($headers as $index => $_) {
                    $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
                    $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
                }
            }

            $selectedOrderFieldValues = $this->orderList->getSelectedOrderFieldValues($orders, $sheet);
            $fromDate = isset($selectedOrderFieldValues['minDate'])
                ? date('d-m-Y', $selectedOrderFieldValues['minDate'])
                : '';

            $toDate = isset($selectedOrderFieldValues['maxDate'])
                ? date('d-m-Y', $selectedOrderFieldValues['maxDate'])
                : '';
            
            $fileName = 'order_export_' . date('d-m-Y_h:i_A') . '.' . $fileFormat;
            $fileContent = null;
            $filePath = '';

            if ($saveToFile) {
                // Save file to var/exportorder
                $exportDir = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/exportorder';
                if (!$this->fileDriver->isDirectory($exportDir)) {
                    $this->fileDriver->createDirectory($exportDir);
                }

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
            }

            // If email export is enabled, send file either from path or memory
            if ($this->orderList->getConfigValue(OrderList::XML_PATH_ORDER_EXPORT_FILE_EMAIL)) {
                if ($saveToFile) {
                    // Send file from saved path
                    $this->orderList->sendOrderReportViaEmail(
                        $fileFormat,
                        $fileName,
                        $filePath,
                        null,
                        $fromDate,
                        $toDate
                    );
                } else {
                    // Generate file content in memory
                    ob_start(); // phpcs:ignore
                    $writer = $fileFormat === 'xlsx' ? new Xlsx($spreadsheet) : new Csv($spreadsheet);
                    if ($writer instanceof Csv) {
                        $writer->setDelimiter(',');
                        $writer->setEnclosure('"');
                        $writer->setLineEnding("\r\n");
                    }
                    $writer->save('php://output');
                    $fileContent = ob_get_clean();

                    // Send file content from memory
                    $this->orderList->sendOrderReportViaEmail(
                        $fileFormat,
                        $fileName,
                        null,
                        $fileContent,
                        $fromDate,
                        $toDate
                    );
                }
            }

            if (!$saveToFile && !$fileContent) {
                ob_start(); // phpcs:ignore
                $writer = $fileFormat === 'xlsx' ? new Xlsx($spreadsheet) : new Csv($spreadsheet);
                if ($writer instanceof Csv) {
                    $writer->setDelimiter(',');
                    $writer->setEnclosure('"');
                    $writer->setLineEnding("\r\n");
                }
                $writer->save('php://output');
                $fileContent = ob_get_clean();
            }

            // Return download response
            if ($saveToFile) {
                return $this->fileFactory->create(
                    $fileName,
                    ['type' => 'filename', 'value' => 'exportorder/' . $fileName, 'rm' => false],
                    DirectoryList::VAR_DIR,
                    $fileFormat === 'xlsx'
                        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        : 'text/csv'
                );
            } else {
                return $this->fileFactory->create(
                    $fileName,
                    ['type' => 'string', 'value' => $fileContent, 'rm' => true],
                    DirectoryList::VAR_DIR,
                    $fileFormat === 'xlsx'
                        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        : 'text/csv'
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error(__('MassExport Error: %1', $e->getMessage()));
            $this->messageManager->addErrorMessage(__('Something went wrong while exporting orders.'));
            return $this->_redirect('sales/order/index');
        }
    }
}
