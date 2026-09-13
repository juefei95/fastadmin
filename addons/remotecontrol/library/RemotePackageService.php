<?php

namespace addons\remotecontrol\library;

use think\Db;
use think\Exception;

class RemotePackageService
{
    public function getEnabledList()
    {
        return Db::name('remote_package')
            ->where('status', 'normal')
            ->order('weigh desc,id asc')
            ->select();
    }

    public function getEnabledPackage($packageId)
    {
        $package = Db::name('remote_package')
            ->where('id', (int)$packageId)
            ->where('status', 'normal')
            ->find();

        if (!$package) {
            throw new Exception('Package not found or disabled');
        }

        return $package;
    }

    public function snapshot($package)
    {
        return [
            'package_id'   => (int)$package['id'],
            'package_name' => $package['name'],
            'days'         => (int)$package['days'],
            'amount'       => $package['price'],
        ];
    }
}
