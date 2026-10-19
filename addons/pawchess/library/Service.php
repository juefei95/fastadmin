<?php
namespace addons\pawchess\library;

use app\common\library\Auth;
use think\Db;

final class Service
{
    private static function table($name) { return Db::name('paw_chess_'.$name); }
    private static function enc($v) { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    private static function dec($v,$fallback=null) { return $v===null?$fallback:json_decode($v,true); }
    public static function fail($status,$message) { throw new ApiError($status,$message); }
    private static function now() { return (int)floor(microtime(true)*1000); }
    private static function user($p) { return ['id'=>(string)$p['id'],'name'=>$p['name'],'points'=>(int)$p['points'],'games'=>(int)$p['games'],'wins'=>(int)$p['wins'],'draws'=>(int)$p['draws']]; }
    private static function pet($p) { return in_array($p,['milk','coal','orange','ragdoll'],true)?$p:'milk'; }
    private static function active($id) { return self::table('room')->where('status','in',['waiting','ready','active'])->where(function($q) use($id) { $q->where('red_id',$id)->whereOr('blue_id',$id); })->find(); }
    public static function login($data) {
        $dev=getenv('PAW_CHESS_DEV_AUTH')==='1' && getenv('APP_ENV')!=='production';
        if ($dev) { if (!isset($data['deviceId']) || !preg_match('/^[a-f0-9]{32,64}$/',$data['deviceId'])) self::fail(400,'缺少有效设备身份'); $identity='dev:'.$data['deviceId']; }
        else {
            if (empty($data['code']) || !is_string($data['code'])) self::fail(400,'缺少微信登录凭证');
            $config=function_exists('get_addon_config')?(array)get_addon_config('pawchess'):[];
            $config['wechat_app_id']=$config['wechat_app_id']??getenv('WECHAT_APP_ID');
            $config['wechat_app_secret']=$config['wechat_app_secret']??getenv('WECHAT_APP_SECRET');
            try { $login=MiniProgramLogin::login($data['code'],$config); }
            catch (\Throwable $e) { \think\Log::record('pawchess third login failed: '.get_class($e),'error');self::fail(401,'微信登录或会员授权失败，请检查配置后重试'); }
            $memberId=(int)$login['user']['id'];
            $p=self::table('player')->where('member_id',$memberId)->find();
            if (!$p) {
                try { self::table('player')->insert(['member_id'=>$memberId,'name'=>'毛球'.str_pad((string)($memberId%10000),4,'0',STR_PAD_LEFT),'points'=>1000,'games'=>0,'wins'=>0,'draws'=>0,'created_at'=>self::now()]); }
                catch (\Exception $e) { /* A concurrent first login may have created this player. */ }
                $p=self::table('player')->where('member_id',$memberId)->find();
            }
            if (!$p) self::fail(500,'创建棋手资料失败');
            $token=$login['token'];
            self::table('session')->insert(['token_hash'=>hash('sha256',$token),'player_id'=>$p['id'],'expires_at'=>self::now()+7*86400000]);
            $active=self::active($p['id']);return ['token'=>$token,'user'=>self::user($p),'development'=>false,'activeRoom'=>$active?$active['id']:null];
        }
        $hash=hash('sha256',$identity);$mapping=self::table('dev_identity')->where('identity_hash',$hash)->find();$p=$mapping?self::table('player')->where('id',$mapping['player_id'])->find():null;
        if (!$p) { try { $playerId=self::table('player')->insertGetId(['member_id'=>null,'name'=>'毛球'.strtoupper(substr($hash,-4)),'points'=>1000,'games'=>0,'wins'=>0,'draws'=>0,'created_at'=>self::now()]);self::table('dev_identity')->insert(['identity_hash'=>$hash,'player_id'=>$playerId,'created_at'=>self::now()]); } catch (\Exception $e) {} $mapping=self::table('dev_identity')->where('identity_hash',$hash)->find();$p=$mapping?self::table('player')->where('id',$mapping['player_id'])->find():null; }
        if (!$p) self::fail(500,'创建开发棋手资料失败');
        $token=bin2hex(random_bytes(32));self::table('session')->insert(['token_hash'=>hash('sha256',$token),'player_id'=>$p['id'],'expires_at'=>self::now()+7*86400000]);
        $active=self::active($p['id']);return ['token'=>$token,'user'=>self::user($p),'development'=>$dev,'activeRoom'=>$active?$active['id']:null];
    }
    public static function auth($token) { $token=trim((string)$token);if (stripos($token,'Bearer ')===0) $token=trim(substr($token,7));if ($token==='') self::fail(401,'登录已过期，请重新连接');$s=self::table('session')->where('token_hash',hash('sha256',$token))->where('expires_at','>',self::now())->find();if (!$s) self::fail(401,'登录已过期，请重新连接');$p=self::table('player')->where('id',$s['player_id'])->find();if (!$p) self::fail(401,'登录已过期，请重新连接');if (!empty($p['member_id']) && !Auth::instance()->init($token)) self::fail(401,'登录已过期，请重新连接');return $p; }
    public static function me($p) { $r=self::active($p['id']);return ['user'=>self::user($p),'activeRoom'=>$r?$r['id']:null,'development'=>getenv('PAW_CHESS_DEV_AUTH')==='1']; }
    private static function player($id) { return $id?self::table('player')->where('id',$id)->find():null; }
    private static function view($r,$p) {
        $side=$r['red_id']==$p['id']?'r':'b';$players=[];
        foreach (['r','b'] as $s) { $id=$r[$s==='r'?'red_id':'blue_id'];$u=self::player($id);$players[$s]=$u?array_merge(self::user($u),['pet'=>$r[$s==='r'?'red_pet':'blue_pet'],'online'=>self::now()-(int)$r[$s==='r'?'red_seen':'blue_seen']<15000]):null; }
        return ['id'=>$r['id'],'code'=>$r['code'],'status'=>$r['status'],'mySide'=>$side,'players'=>$players,'board'=>self::dec($r['board_json']),
            'turn'=>$r['turn'],'moves'=>self::dec($r['moves_json'],[]),'captures'=>self::dec($r['captures_json']),
            'last'=>self::dec($r['last_json']),'revision'=>(int)$r['revision'],'started'=>(int)($r['started_at']?:$r['created_at']),
            'result'=>self::dec($r['result_json']),'rating'=>self::dec($r['rating_json']),'deadline'=>(int)$r['deadline'],
            'serverTime'=>self::now(),'turnMs'=>(int)$r['turn_ms'],'rated'=>(bool)$r['rated'],'ready'=>self::dec($r['ready_json']),
            'rematchReady'=>self::dec($r['rematch_ready_json']),'rematchId'=>$r['rematch_id'],'series'=>self::dec($r['series_json']),'endedAt'=>(int)$r['ended_at']];
    }
    private static function persist($r) { self::table('room')->where('id',$r['id'])->update($r); }
    private static function newRoom($red,$blue,$redPet,$bluePet,$minutes,$rated,$series=null,$active=false) {
        $b=Rules::initial();$now=self::now();$code=null;
        for ($i=0;$i<20;$i++) { $candidate=(string)random_int(100000,999999);if (!self::table('room')->where('active_code',$candidate)->find()) { $code=$candidate;break; } }
        if (!$code) self::fail(503,'棋馆有点满，请稍后再来');
        return ['id'=>bin2hex(random_bytes(12)),'code'=>$code,'active_code'=>$code,'status'=>$active?'active':($blue?'ready':'waiting'),
            'red_id'=>$red,'blue_id'=>$blue,'red_pet'=>$redPet,'blue_pet'=>$bluePet,'red_seen'=>$now,'blue_seen'=>$blue?$now:null,
            'board_json'=>self::enc($b),'moves_json'=>'[]','captures_json'=>self::enc(['r'=>[],'b'=>[]]),'positions_json'=>self::enc([implode(',',$b).'r']),
            'turn'=>'r','revision'=>$active?1:0,'quiet'=>0,'turn_ms'=>$minutes*60000,'deadline'=>$active?$now+$minutes*60000:0,
            'rated'=>$rated?1:0,'ready_json'=>self::enc(['r'=>$active,'b'=>$active]),'rematch_ready_json'=>'{}','rematch_id'=>null,
            'series_json'=>self::enc($series?:['games'=>0,'wins'=>new \stdClass(),'draws'=>0]),'result_json'=>null,'rating_json'=>null,'last_json'=>null,
            'created_at'=>$now,'started_at'=>$active?$now:null,'ended_at'=>null];
    }
    public static function create($p,$data) { if (self::active($p['id'])) self::fail(409,'已有进行中的房间，请先返回对局');$minutes=$data['minutes']??3;if (!in_array($minutes,[1,3,5],true)) self::fail(400,'每步用时请选择 1、3 或 5 分钟');$rated=$data['rated']??true;if (!is_bool($rated)) self::fail(400,'计分设置无效');$r=self::newRoom($p['id'],null,self::pet($data['pet']??null),null,$minutes,$rated);self::table('room')->insert($r);return self::view($r,$p); }
    public static function join($p,$data) { return Db::transaction(function() use($p,$data) { $r=self::table('room')->where('active_code',(string)($data['code']??''))->lock(true)->find();if (!$r || $r['status']!=='waiting') self::fail(404,'房间不存在、已开始或已过期');if (self::active($p['id'])) self::fail(409,'已有进行中的房间，请先返回对局');if ($r['red_id']==$p['id']) self::fail(409,'这是自己的房间，请邀请另一位棋友');$r['blue_id']=$p['id'];$r['blue_pet']=self::pet($data['pet']??null);$r['blue_seen']=self::now();$r['status']='ready';$r['revision']++;self::persist($r);return self::view($r,$p); }); }
    private static function settle(&$r,$result) { if ($r['status']==='ended') return;$r['status']='ended';$r['active_code']=null;$r['result_json']=self::enc($result);$r['ended_at']=self::now();$r['revision']++;$moves=self::dec($r['moves_json'],[]);$rated=$r['rated'] && $r['blue_id'] && count($moves)>=4;$delta=0;
        $series=self::dec($r['series_json']);$series['games']++;if ($result['winner']==='draw') $series['draws']++;else { $id=$r[$result['winner']==='r'?'red_id':'blue_id'];$series['wins'][(string)$id]=($series['wins'][(string)$id]??0)+1; }$r['series_json']=self::enc($series);
        if ($rated) { $a=self::table('player')->where('id',$r['red_id'])->lock(true)->find();$b=self::table('player')->where('id',$r['blue_id'])->lock(true)->find();$score=$result['winner']==='draw'?0.5:($result['winner']==='r'?1:0);$delta=(int)round(32*($score-1/(1+pow(10,($b['points']-$a['points'])/400))));foreach ([[$a,$delta,$score===1,$score===0.5],[$b,-$delta,$score===0,$score===0.5]] as $item) self::table('player')->where('id',$item[0]['id'])->update(['points'=>$item[0]['points']+$item[1],'games'=>$item[0]['games']+1,'wins'=>$item[0]['wins']+($item[2]?1:0),'draws'=>$item[0]['draws']+($item[3]?1:0)]); }
        $r['rating_json']=self::enc(['rated'=>(bool)$rated,'deltas'=>['r'=>$delta,'b'=>-$delta]]);self::persist($r);
        $matchId=self::table('match')->insertGetId(['room_id'=>$r['id'],'red_id'=>$r['red_id'],'blue_id'=>$r['blue_id'],'winner'=>$result['winner'],'reason'=>$result['reason'],'rated'=>$rated?1:0,'red_delta'=>$delta,'blue_delta'=>-$delta,'moves_json'=>$r['moves_json'],'created_at'=>self::now()]);
        if ($rated) foreach ([[$a,$delta],[$b,-$delta]] as $item) self::table('rating_log')->insert(['match_id'=>$matchId,'player_id'=>$item[0]['id'],'delta'=>$item[1],'points_before'=>$item[0]['points'],'points_after'=>$item[0]['points']+$item[1],'created_at'=>self::now()]);
    }
    public static function room($p,$id,$method='GET',$action=null,$data=[]) { return Db::transaction(function() use($p,$id,$method,$action,$data) { $r=self::table('room')->where('id',$id)->lock(true)->find();if (!$r) self::fail(404,'房间已结束或不存在');$side=$r['red_id']==$p['id']?'r':($r['blue_id']==$p['id']?'b':null);if (!$side) self::fail(403,'你不在这个房间');$r[$side==='r'?'red_seen':'blue_seen']=self::now();
        if ($r['status']==='active' && self::now()>$r['deadline']) self::settle($r,['winner'=>Rules::opposite($r['turn']),'reason'=>($r['turn']==='r'?'红方':'蓝方').'思考超时']);
        if (in_array($r['status'],['waiting','ready'],true) && self::now()-$r['created_at']>1800000) { $r['status']='closed';$r['active_code']=null;$r['revision']++; }
        if ($method==='GET' && !$action) { self::persist($r);return self::view($r,$p); }
        if ($method!=='POST') self::fail(405,'请求方式无效');
        if ($action==='ready') { if (!in_array($r['status'],['waiting','ready'],true)) self::fail(409,'当前房间不能准备');$ready=self::dec($r['ready_json']);if (!$ready[$side]) { $ready[$side]=true;$r['revision']++; }$r['ready_json']=self::enc($ready);if ($r['blue_id'] && $ready['r'] && $ready['b']) { $r['status']='active';$r['started_at']=self::now();$r['deadline']=self::now()+$r['turn_ms'];$r['revision']++; } }
        elseif ($action==='move') { if ($r['status']!=='active') self::fail(409,'对局尚未开始或已经结束');if ($r['turn']!==$side) self::fail(409,'还没轮到你');if (($data['revision']??null)!==(int)$r['revision']) self::fail(409,'棋盘已变化，请等待同步');$from=$data['from']??null;$to=$data['to']??null;if (!is_int($from)||!is_int($to)||$from<0||$from>89||$to<0||$to>89) self::fail(400,'走棋参数无效');$board=self::dec($r['board_json']);if (!in_array(['from'=>$from,'to'=>$to],Rules::legal($board,$side),true)) self::fail(400,'这一步不符合象棋规则');$piece=$board[$from];$captured=$board[$to];$text=Rules::moveText($board,$from,$to);$board=Rules::apply($board,$from,$to);$moves=self::dec($r['moves_json'],[]);$moves[]=['from'=>$from,'to'=>$to,'side'=>$side,'captured'=>$captured,'text'=>$text,'special'=>null];$captures=self::dec($r['captures_json']);if ($captured) $captures[$side][]=$captured;$r['board_json']=self::enc($board);$r['moves_json']=self::enc($moves);$r['captures_json']=self::enc($captures);$r['last_json']=self::enc(['from'=>$from,'to'=>$to]);$r['turn']=Rules::opposite($side);$r['revision']++;$r['quiet']=$captured||$piece[1]==='p'?0:$r['quiet']+1;$r['deadline']=self::now()+$r['turn_ms'];$positions=self::dec($r['positions_json']);$position=implode(',',$board).$r['turn'];$positions[]=$position;$r['positions_json']=self::enc($positions);$result=Rules::outcome($board,$r['turn']);if (!$result && count(array_filter($positions,function($x)use($position){return $x===$position;}))>=3) $result=['winner'=>'draw','reason'=>'三次同一局面'];if (!$result && ($r['quiet']>=120||count($moves)>=600)) $result=['winner'=>'draw','reason'=>'休闲规则判和'];if ($result) self::settle($r,$result); }
        elseif ($action==='resign'||$action==='leave') { if (in_array($r['status'],['waiting','ready'],true)) { $r['status']='closed';$r['active_code']=null;$r['revision']++; } elseif ($r['status']==='active') self::settle($r,['winner'=>Rules::opposite($side),'reason'=>($side==='r'?'红方':'蓝方').($action==='resign'?'认输':'离开棋局')]);elseif ($action==='leave' && $r['status']==='ended') { $ready=self::dec($r['rematch_ready_json'],[]);unset($ready[$side]);$r['rematch_ready_json']=self::enc($ready);$r['revision']++; } }
        elseif ($action==='rematch') { if ($r['status']!=='ended') self::fail(409,'本局结束后才能再战');if (!$r['rematch_id']) { $ready=self::dec($r['rematch_ready_json'],[]);if (empty($ready[$side])) { $ready[$side]=true;$r['revision']++; }$r['rematch_ready_json']=self::enc($ready);if (!empty($ready['r'])&&!empty($ready['b'])) { $next=self::newRoom($r['blue_id'],$r['red_id'],$r['blue_pet'],$r['red_pet'],intdiv($r['turn_ms'],60000),(bool)$r['rated'],self::dec($r['series_json']),true);self::table('room')->insert($next);$r['rematch_id']=$next['id'];$r['revision']++; } } }
        else self::fail(404,'接口不存在');self::persist($r);return self::view($r,$p); }); }
    public static function ranking($p,$page) { $page=max(0,min(999,(int)$page));$total=self::table('player')->where('games','>',0)->count();$all=self::table('player')->where('games','>',0)->order('points desc,wins desc,id asc')->select();$rank=null;foreach ($all as $i=>$row) if ($row['id']==$p['id']) $rank=$i+1;$rows=[];foreach (array_slice($all,$page*8,8) as $i=>$row) $rows[]=array_merge(self::user($row),['rank'=>$page*8+$i+1]);return ['rows'=>$rows,'page'=>$page,'total'=>$total,'mine'=>array_merge(self::user(self::player($p['id'])),['rank'=>$rank]),'development'=>getenv('PAW_CHESS_DEV_AUTH')==='1']; }
    public static function sweep() {
        $now=self::now();$rooms=self::table('room')->where(function($q) use($now) {
            $q->where('status','active')->where('deadline','<',$now);
        })->whereOr(function($q) use($now) {
            $q->where('status','in',['waiting','ready'])->where('created_at','<',$now-1800000);
        })->order('created_at asc')->limit(200)->select();
        $count=0;foreach ($rooms as $r) { $p=self::player($r['red_id']);if (!$p) continue;self::room($p,$r['id']);$count++; }
        self::table('session')->where('expires_at','<',$now)->delete();
        return $count;
    }
}

class ApiError extends \RuntimeException { public $status; public function __construct($status,$message) { parent::__construct($message);$this->status=$status; } }
