<?php

namespace addons\remotecontrol\controller;

use app\common\controller\Api;

class Announcement extends Api
{
    protected $noNeedLogin = ['index'];
    protected $noNeedRight = '*';

    public function index()
    {
        $config = (array)get_addon_config('remotecontrol');
        $items = [];
        if (!empty($config['announcement_enabled'])) {
            $lines = preg_split('/\r\n|\r|\n/', (string)($config['announcement_text'] ?? ''));
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $items[] = $line;
                }
            }
        }
        $this->success('ok', ['items' => $items]);
    }
}
