<?php
namespace addons\checkin;

use think\Addons;

class Checkin extends Addons
{
    public function install() { return true; }
    public function uninstall() { return true; }
    public function enable() { return true; }
    public function disable() { return true; }
}
