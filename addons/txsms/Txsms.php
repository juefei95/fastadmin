<?php

namespace addons\txsms;

use addons\txsms\library\TencentCloudSmsClient;
use app\common\library\Menu;
use think\Addons;
use think\Log;

/**
 * 插件
 */
class Txsms extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {

        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {

        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable()
    {

        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disable()
    {

        return true;
    }

    /**
     * 短信发送行为
     * @param array $params 必须包含mobile,event,code
     * @return  boolean
     */
    public function smsSend(&$params)
    {
        $config = get_addon_config('txsms');
        if (!isset($config['template'][$params['event']])) {
            return false;
        }
        $result = false;

        // 创建短信客户端实例
        $smsClient = new TencentCloudSmsClient();
        try {
            $result = $smsClient
                ->setPhoneNumbers($params['mobile'])
                ->setSmsSdkAppId($config['smsSdkAppId'])
                ->setTemplateId($config['template'][$params['event']])
                ->setSignName($config['signName'])
                ->setTemplateParams([$params['code']])
                ->sendSms();
        } catch (\Exception $e) {
            $smsClient->setError($e->getCode(), $e->getMessage());
        }
        if (!$result && config('app_debug')) {
            Log::record($smsClient->getError());
        }
        return $result;
    }

    /**
     * 短信发送通知
     * @param array $params 必须包含 mobile,event,msg
     * @return  boolean
     */
    public function smsNotice(&$params)
    {
        $config = get_addon_config('txsms');
        if (isset($params['msg'])) {
            if (is_array($params['msg'])) {
                $param = $params['msg'];
            } else {
                parse_str($params['msg'], $param);
            }
        } else {
            $param = [];
        }
        $param = $param ?: [];
        $templateId = $params['template'] ?? (isset($params['event']) && isset($config['template'][$params['event']]) ? $config['template'][$params['event']] : '');

        $result = false;
        // 创建短信客户端实例
        $smsClient = new TencentCloudSmsClient();
        try {
            $result = $smsClient
                ->setPhoneNumbers($params['mobile'])
                ->setSmsSdkAppId($config['smsSdkAppId'])
                ->setTemplateId($templateId)
                ->setSignName($config['signName'])
                ->setTemplateParams($param)
                ->sendSms();
        } catch (\Exception $e) {
            $smsClient->setError($e->getCode(), $e->getMessage());
        }

        if (!$result && config('app_debug')) {
            Log::record($smsClient->getError());
        }
        return $result;
    }

    /**
     * 检测验证是否正确
     * @param   $params
     * @return  boolean
     */
    public function smsCheck(&$params)
    {
        return true;
    }

}
