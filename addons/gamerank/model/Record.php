<?php

namespace addons\gamerank\model;

use think\Model;

class Record extends Model
{
    protected $name = 'game_rank_record';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
