<?php

namespace addons\gamerank\model;

use think\Model;

class Player extends Model
{
    protected $name = 'game_rank_player';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
