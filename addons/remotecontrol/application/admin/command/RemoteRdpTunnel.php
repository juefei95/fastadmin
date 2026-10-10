<?php

namespace app\admin\command;

use addons\remotecontrol\library\RemoteRdpTunnelService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class RemoteRdpTunnel extends Command
{
    protected function configure()
    {
        $this->setName('remote:sync-expired-rdp-tunnels')
            ->setDescription('Sync expired and stopped Windows Remote Desktop tunnels');
    }

    protected function execute(Input $input, Output $output)
    {
        $service = new RemoteRdpTunnelService();
        $marked = $service->markInvalidTunnels();
        $released = $service->reconcileStoppingTunnels();
        $output->writeln("Marked {$marked['marked']} expired RDP tunnel(s) for shutdown; released {$released['released']} stopped tunnel(s).");
    }
}
