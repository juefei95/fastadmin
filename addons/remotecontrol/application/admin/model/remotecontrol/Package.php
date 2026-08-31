<?php

namespace app\admin\model\remotecontrol;

use think\Model;


class Package extends Model
{

    

    

    // 表名
    protected $name = 'remote_package';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'recommended_text',
        'status_text'
    ];

    // 类型转换
    protected $type = [

    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            if (!$row['weigh']) {
                $pk = $row->getPk();
                $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
            }
        });
    }

    
    public function getRecommendedList()
    {
        return ['0' => __('Recommended 0'), '1' => __('Recommended 1')];
    }

    public function getStatusList()
    {
        return ['30' => __('Status 30')];
    }


    public function getRecommendedTextAttr($value, $data)
    {
        $value = $value ?: ($data['recommended'] ?? '');
        $list = $this->getRecommendedList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }




}
