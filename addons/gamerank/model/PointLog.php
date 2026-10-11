<?php

namespace addons\gamerank\model;

use think\Model;

class PointLog extends Model
{
    protected $name = 'game_rank_point_log';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = false;
}
