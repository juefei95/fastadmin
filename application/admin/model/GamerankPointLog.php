<?php

namespace app\admin\model;

use think\Model;

class GamerankPointLog extends Model
{
    protected $name = 'game_rank_point_log';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = false;

    public function user()
    {
        return $this->belongsTo('\app\common\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
