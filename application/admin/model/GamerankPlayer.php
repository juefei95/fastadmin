<?php

namespace app\admin\model;

use think\Model;

class GamerankPlayer extends Model
{
    protected $name = 'game_rank_player';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function user()
    {
        return $this->belongsTo('\app\common\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
