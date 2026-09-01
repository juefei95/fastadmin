<?php

namespace addons\txsms\library;

use Exception;

/**
 * 腾讯云短信服务API调用类
 */
class TencentCloudSmsClient
{
    private $secretId;
    private $secretKey;
    private $token;
    private $service = "sms";
    private $host = "sms.tencentcloudapi.com";
    private $endpoint = "https://sms.tencentcloudapi.com";
    private $version = "2021-01-11";
    private $region = "ap-beijing";
    private $algorithm = "TC3-HMAC-SHA256";

    private $phoneNumbers = [];
    private $smsSdkAppId = "";
    private $templateId = "";
    private $signName = "";
    private $templateParams = [];

    private $errorCode = '';
    private $errorMessage = '';

    /**
     * 构造函数
     *
     * @param string $secretId  腾讯云账户SecretId
     * @param string $secretKey 腾讯云账户SecretKey
     * @param string $region    区域，默认为ap-beijing
     * @param string $token     临时token，可选
     */
    public function __construct($secretId = '', $secretKey = '', $region = '', $token = '')
    {
        // 安全提示：建议使用更安全的方式来使用密钥，如从环境变量读取
        // 参考：https://cloud.tencent.com/document/product/1278/85305
        $config = get_addon_config('txsms');
        $this->secretId = $secretId ?: $config['secretId'];
        $this->secretKey = $secretKey ?: $config['secretKey'];
        $this->region = $region ?: $this->region;
        $this->token = $token;
    }

    /**
     * 设置手机号码
     *
     * @param array|string $phoneNumbers 手机号码，可以是数组或单个字符串
     * @return $this
     */
    public function setPhoneNumbers($phoneNumbers)
    {
        if (is_string($phoneNumbers)) {
            $this->phoneNumbers = explode(",", $phoneNumbers);
        } else {
            $this->phoneNumbers = $phoneNumbers;
        }
        return $this;
    }

    /**
     * 设置短信应用ID
     *
     * @param string $appId 短信应用ID
     * @return $this
     */
    public function setSmsSdkAppId($appId)
    {
        $this->smsSdkAppId = $appId;
        return $this;
    }

    /**
     * 设置短信模板ID
     *
     * @param string $templateId 短信模板ID
     * @return $this
     */
    public function setTemplateId($templateId)
    {
        $this->templateId = $templateId;
        return $this;
    }

    /**
     * 设置短信签名
     *
     * @param string $signName 短信签名
     * @return $this
     */
    public function setSignName($signName)
    {
        $this->signName = $signName;
        return $this;
    }

    /**
     * 设置模板参数
     *
     * @param array $params 模板参数
     * @return $this
     */
    public function setTemplateParams(array $params)
    {
        $params = array_map("strval", $params);
        $this->templateParams = $params;
        return $this;
    }

    /**
     * 发送短信
     *
     * @param string $action API动作，默认为SendSms
     * @return bool 成功返回接口结果，失败返回false
     * @throws Exception 请求异常
     */
    public function sendSms($action = "SendSms")
    {
        // 构建请求参数
        $payload = $this->buildPayload();

        // 构建请求头和签名
        $headers = $this->buildRequestHeaders($action, $payload);

        // 发送请求
        $response = $this->sendRequest($payload, $headers);

        // 解析响应，检查是否有错误
        $result = json_decode($response, true);
        if (isset($result['Response']['Error'])) {
            $this->errorCode = $result['Response']['Error']['Code'] ?? '';
            $this->errorMessage = $result['Response']['Error']['Message'] ?? '';
            return false;
        }

        return true;
    }

    public function setError($code, $message)
    {
        $this->errorCode = $code;
        $this->errorMessage = $message;
    }

    /**
     * 获取错误信息
     *
     * @return array 包含错误码和错误信息的数组
     */
    public function getError()
    {
        return [
            'code'    => $this->errorCode,
            'message' => $this->errorMessage
        ];
    }

    /**
     * 构建请求参数
     *
     * @return string JSON格式的请求参数
     */
    private function buildPayload()
    {
        $params = [
            "PhoneNumberSet" => $this->phoneNumbers,
            "SmsSdkAppId"    => $this->smsSdkAppId,
            "TemplateId"     => $this->templateId,
            "SignName"       => $this->signName
        ];
        // 如果有模板参数，则添加
        if (!empty($this->templateParams)) {
            $params["TemplateParamSet"] = $this->templateParams;
        }

        return json_encode($params);
    }

    /**
     * 构建请求头和签名
     *
     * @param string $action  API动作
     * @param string $payload 请求参数
     * @return array 请求头数组
     */
    private function buildRequestHeaders($action, $payload)
    {
        $timestamp = time();
        $date = gmdate("Y-m-d", $timestamp);

        // 步骤1：拼接规范请求串
        $httpRequestMethod = "POST";
        $canonicalUri = "/";
        $canonicalQuerystring = "";
        $ct = "application/json; charset=utf-8";
        $canonicalHeaders = "content-type:" . $ct . "\nhost:" . $this->host . "\nx-tc-action:" . strtolower($action) . "\n";
        $signedHeaders = "content-type;host;x-tc-action";
        $hashedRequestPayload = hash("sha256", $payload);
        $canonicalRequest = "$httpRequestMethod\n$canonicalUri\n$canonicalQuerystring\n$canonicalHeaders\n$signedHeaders\n$hashedRequestPayload";

        // 步骤2：拼接待签名字符串
        $credentialScope = "$date/$this->service/tc3_request";
        $hashedCanonicalRequest = hash("sha256", $canonicalRequest);
        $stringToSign = "$this->algorithm\n$timestamp\n$credentialScope\n$hashedCanonicalRequest";

        // 步骤3：计算签名
        $secretDate = $this->sign("TC3" . $this->secretKey, $date);
        $secretService = $this->sign($secretDate, $this->service);
        $secretSigning = $this->sign($secretService, "tc3_request");
        $signature = hash_hmac("sha256", $stringToSign, $secretSigning);

        // 步骤4：拼接Authorization
        $authorization = "$this->algorithm Credential=$this->secretId/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

        // 步骤5：构建请求头
        $headers = [
            "Authorization"  => $authorization,
            "Content-Type"   => "application/json; charset=utf-8",
            "Host"           => $this->host,
            "X-TC-Action"    => $action,
            "X-TC-Timestamp" => $timestamp,
            "X-TC-Version"   => $this->version
        ];

        if ($this->region) {
            $headers["X-TC-Region"] = $this->region;
        }

        if ($this->token) {
            $headers["X-TC-Token"] = $this->token;
        }

        return $headers;
    }

    /**
     * 发送HTTP请求
     *
     * @param string $payload 请求参数
     * @param array  $headers 请求头
     * @return string 接口返回结果
     * @throws Exception 请求异常
     */
    private function sendRequest($payload, $headers)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) {
                return "$k: $v";
            }, array_keys($headers), $headers));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);

            if ($response === false) {
                throw new Exception(curl_error($ch));
            }

            curl_close($ch);
            return $response;
        } catch (Exception $err) {
            throw $err;
        }
    }

    /**
     * HMAC-SHA256签名
     *
     * @param string $key 密钥
     * @param string $msg 消息
     * @return string 签名结果
     */
    private function sign($key, $msg)
    {
        return hash_hmac("sha256", $msg, $key, true);
    }
}