<?php

namespace app\admin\command;

use addons\remotecontrol\library\RemotePaymentService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class RemoteOrder extends Command
{
    protected function configure()
    {
        $this
            ->setName('remote:close-expired-orders')
            ->setDescription('Close expired remote payment orders');
    }

    protected function execute(Input $input, Output $output)
    {
        $result = (new RemotePaymentService())->closeExpiredPendingOrders();
        $output->writeln("Closed {$result['closed']} expired payment order(s).");
        if ($result['failed']) {
            $output->writeln('Failed: ' . implode(', ', $result['failed']));
        }
    }
}
