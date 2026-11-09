<?php

namespace app\admin\controller;

use app\admin\model\AdminLog;
use app\common\controller\Backend;
use app\common\exception\UploadException;
use app\common\library\Upload;
use think\Config;

class Epay extends Backend
{
    protected $noNeedRight = ['upload'];

    /**
     * 上传本地证书
     * @return void
     */
    public function upload()
    {
        AdminLog::setTitle('上传证书');
        Config::set('default_return_type', 'json');

        //检测是否有addon/config的权限
        if (!$this->auth->check('addon/config')) {
            $this->error('暂无权限');
        }

        $certname = $this->request->post('certname', '');
        $certPathArr = [
            'cert_client'         => '/addons/epay/certs/apiclient_cert.pem', //微信支付api
            'cert_key'            => '/addons/epay/certs/apiclient_key.pem', //微信支付api
            'public_key'          => '/addons/epay/certs/public_key.pem', //微信公钥证书
            'app_cert_public_key' => '/addons/epay/certs/appCertPublicKey.crt',//应用公钥证书路径
            'alipay_root_cert'    => '/addons/epay/certs/alipayRootCert.crt', //支付宝根证书路径
            'ali_public_key'      => '/addons/epay/certs/alipayCertPublicKey.crt', //支付宝公钥证书路径
        ];
        if (!isset($certPathArr[$certname])) {
            $this->error("证书错误");
        }
        $url = $certPathArr[$certname];
        $file = $this->request->file('file');
        if (!$file) {
            $this->error("未上传文件");
        }

        //验证文件大小限制和后缀限制
        if (!$file->check([
            'size' => $this->convertToBytes(config('upload.maxsize')),
            'ext'  => 'pem,crt',
        ])) {
            $this->error($file->getError());
        }

        //验证上传的文件内容是否符合pem,crt格式内容
        $fileContent = file_get_contents($file->getInfo('tmp_name'));
        if (!preg_match('/-----BEGIN(.*)-----[\s\S]+-----END(.*)-----/', $fileContent)) {
            $this->error("文件内容错误");
        }

        $file->move(dirname(ROOT_PATH . $url), basename(ROOT_PATH . $url), true);
        $this->success(__('上传成功'), '', ['url' => $url]);
    }

    protected function convertToBytes($size)
    {
        $units = ['b' => 1, 'k' => 1024, 'kb' => 1024, 'm' => 1048576, 'mb' => 1048576, 'g' => 1073741824, 'gb' => 1073741824];
        preg_match('/^(\d+(?:\.\d+)?)\s*([a-z]+)$/i', strtolower(trim($size)), $matches);
        return intval($matches[1] * $units[$matches[2]]);
    }
}
