<?php

namespace app\admin\model\remotecontrol;

use think\Model;


class Member extends Model
{

    

    

    // 表名
    protected $name = 'remote_member';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'expire_time_text',
        'trial_given_text',
        'control_enabled_text'
    ];

    // 类型转换
    protected $type = [
        'trial_started_at' => 'string',
        'last_paid_at' => 'string',
        'created_at' => 'string',
        'updated_at' => 'string'
    ];
    

    
    public function getTrialGivenList()
    {
        return ['0' => __('Trial_given 0'), '1' => __('Trial_given 1')];
    }

    public function getControlEnabledList()
    {
        return ['0' => __('Control_enabled 0'), '1' => __('Control_enabled 1')];
    }


    public function getExpireTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['expire_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getTrialGivenTextAttr($value, $data)
    {
        $value = $value ?: ($data['trial_given'] ?? '');
        $list = $this->getTrialGivenList();
        return $list[$value] ?? '';
    }


    public function getControlEnabledTextAttr($value, $data)
    {
        $value = $value ?: ($data['control_enabled'] ?? '');
        $list = $this->getControlEnabledList();
        return $list[$value] ?? '';
    }

    protected function setExpireTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
