<?php
namespace addons\pawchess\library;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class ExpireCommand extends Command
{
    protected function configure() { $this->setName('pawchess:expire')->setDescription('结算超时棋局并关闭过期房间'); }
    protected function execute(Input $input,Output $output) { $output->writeln('Processed '.Service::sweep().' rooms'); }
}
