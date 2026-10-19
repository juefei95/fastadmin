<?php
namespace addons\pawchess\controller;

use addons\pawchess\library\Service;
use addons\pawchess\library\ApiError;

class Api extends \think\Controller
{
    public function dispatch()
    {
        $response=null;
        try {
            $request=$this->request;$method=strtoupper($request->method());$path=parse_url($request->url(),PHP_URL_PATH);
            $body=[];
            if ($method==='POST') { $raw=file_get_contents('php://input');if (strlen($raw)>4096) Service::fail(413,'请求太大');$body=$raw===''?[]:json_decode($raw,true);if (!is_array($body) || ($raw!=='' && (!preg_match('/^\s*\{/',$raw) || json_last_error()!==JSON_ERROR_NONE))) Service::fail(400,'请求内容无效'); }
            if ($method==='GET' && $path==='/health') $result=['ok'=>true,'game'=>'毛球象棋社'];
            elseif ($method==='POST' && $path==='/api/session') $result=Service::login($body);
            else { $token=(string)$request->header('token');if ($token==='') $token=(string)$request->header('Authorization');$p=Service::auth($token);
                if ($method==='GET' && $path==='/api/me') $result=Service::me($p);
                elseif ($method==='GET' && $path==='/api/ranking') $result=Service::ranking($p,$request->get('page',0));
                elseif ($method==='POST' && $path==='/api/rooms') $result=Service::create($p,$body);
                elseif ($method==='POST' && $path==='/api/rooms/join') $result=Service::join($p,$body);
                elseif (preg_match('~^/api/rooms/([a-f0-9]{24})(?:/(move|ready|resign|leave|rematch))?$~',$path,$m)) $result=Service::room($p,$m[1],$method,$m[2]??null,$body);
                else Service::fail(404,'接口不存在');
            }
            $response=json($result,200);
        } catch (ApiError $e) { $response=json(['error'=>$e->getMessage()],$e->status); }
        catch (\Throwable $e) { \think\Log::record('pawchess: '.$e->getMessage(),'error');$response=json(['error'=>'服务暂时繁忙，请重试'],500); }
        return $response->header(['Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
