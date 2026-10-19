<?php

namespace app\admin\model;

use think\Model;

class GamerankRecord extends Model
{
    protected $name = 'game_rank_record';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function user()
    {
        return $this->belongsTo('\app\common\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
