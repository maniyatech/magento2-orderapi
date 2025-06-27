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

        foreach ($orders as $order) {
            $output->writeln(str_repeat('-', 50));
            $output->writeln("Order ID       : {$order['increment_id']}");
            $output->writeln("Customer Name  : " . ($order['billing_name'] ?? 'N/A'));
            $output->writeln("Total Amount   : {$order['grand_total']}");
            $output->writeln("Status         : " . ($order['order_status'] ?? 'N/A'));
            $output->writeln("Date           : " . ($order['order_date'] ?? 'N/A'));
            $output->writeln("Payment Method : " . ($order['payment_title'] ?? $order['payment_method'] ?? 'N/A'));
            $output->writeln("Shipping Desc  : " . ($order['shipping_description'] ?? 'N/A'));
            $output->writeln("Shipping Amt   : " . ($order['shipping_amount'] ?? '0'));
            $output->writeln("Shipping Incl  : " . ($order['shipping_incl_tax'] ?? '0'));
        }

        return Command::SUCCESS;
    }
}
