<?php
/**
 * ManiyaTech
 *
 * @author        Milan Maniya
 * @package       ManiyaTech_OrderApi
 */

declare(strict_types=1);

namespace ManiyaTech\OrderApi\Console\Command;

use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ManiyaTech\OrderApi\Model\OrderList;

class OrderListCommand extends Command
{
    /**
     * @var OrderList
     */
    private OrderList $orderList;

    /**
     * @var State
     */
    private State $appState;

    /**
     * OrderListCommand Constructor.
     *
     * @param OrderList $orderList Provides filtered order data.
     * @param State $appState Sets the Magento application area.
     */
    public function __construct(
        OrderList $orderList,
        State $appState
    ) {
        $this->orderList = $orderList;
        $this->appState = $appState;
        parent::__construct();
    }

    /**
     * Configure the CLI command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $grandTotalThreshold = $this->orderList->getConfigValue(OrderList::XML_PATH_GRAND_TOTAL);
        $days = $this->orderList->getConfigValue(OrderList::XML_PATH_EXPORT_DAYS);

        $description = sprintf(
            'List orders with total > %s from last %s days (store timezone)',
            $grandTotalThreshold,
            $days
        );

        $this->setName('order:orderlist')->setDescription($description);
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input Input interface.
     * @param OutputInterface $output Output interface.
     * @return int Exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Throwable $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
        }

        if (!$this->orderList->isModuleEnabled()) {
            $output->writeln('<info>Please enable the module in configuration first.</info>');
            return Command::SUCCESS;
        }

        $orders = $this->orderList->getOrders();

        if (empty($orders)) {
            $output->writeln('<info>No orders found for the given criteria.</info>');
            return Command::SUCCESS;
        }

        $selectedOrderFields = $this->orderList->getSelectedOrderFields();
        $defaultOrderFields = $this->orderList->getDefaultOrderFields();
        $finalFields = $selectedOrderFields ?: $defaultOrderFields;
        
        foreach ($orders as $order) {
            $output->writeln(str_repeat('-', 100));
            foreach ($finalFields as $field) {
                $code = $field['order_code'];
                $value = $order->getData($code) ?? '';
                $lable = $field['order_title'];
                switch ($code) {
                    case 'status':
                        $value = ucfirst((string) $value);
                        $output->writeln("$lable  : {$value}");
                        break;

                    case 'created_at':
                    case 'updated_at':
                        $value = $value ? date('d-m-Y', strtotime((string) $value)) : '';
                        $output->writeln("$lable  : {$value}");
                        break;

                    case 'customer_firstname':
                        $firstname = $order->getData('customer_firstname') ?? '';
                        $lastname = $order->getData('customer_lastname') ?? '';
                        $value = trim($firstname . ' ' . $lastname);
                        $output->writeln("$lable  : {$value}");
                        break;

                    case 'customer_group_id':
                        $groupId = $order->getData('customer_group_id');
                        $value = $this->orderList->getCustomerGroupName((int) $groupId);
                        $output->writeln("$lable  : {$value}");
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
                        $value = $this->orderList->getFormattedPrice((float) $value, $order->getOrderCurrencyCode());
                        $output->writeln("$lable  : {$value}");
                        break;

                    case 'billing_address_id':
                        $value = $this->orderList->formatAddress($order->getBillingAddress());
                        $output->writeln("$lable  : {$value}");
                        break;

                    case 'shipping_address_id':
                        $value = $this->orderList->formatAddress($order->getShippingAddress());
                        $output->writeln("$lable  : {$value}");
                        break;

                    default:
                        $value = (string) $value;
                        $output->writeln("$lable  : {$value}");
                        break;
                }
            }
        }

        return Command::SUCCESS;
    }
}
