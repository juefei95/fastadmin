<?php
namespace addons\pawchess\library;

final class Rules
{
    public static function opposite($side) { return $side === 'r' ? 'b' : 'r'; }
    public static function initial() {
        $b = array_fill(0, 90, null);
        $back = ['r','h','e','a','k','a','e','h','r'];
        for ($x=0;$x<9;$x++) { $b[$x]='b'.$back[$x]; $b[81+$x]='r'.$back[$x]; }
        foreach ([1,7] as $x) { $b[18+$x]='bc'; $b[63+$x]='rc'; }
        foreach ([0,2,4,6,8] as $x) { $b[27+$x]='bp'; $b[54+$x]='rp'; }
        return $b;
    }
    public static function apply($b,$from,$to) { $b[$to]=$b[$from]; $b[$from]=null; return $b; }
    private static function inside($x,$y) { return $x>=0 && $x<9 && $y>=0 && $y<10; }
    private static function palace($x,$y,$s) { return $x>=3 && $x<=5 && ($s==='r' ? $y>=7 && $y<=9 : $y>=0 && $y<=2); }
    public static function pseudo($b,$from) {
        $p=$b[$from]; if (!$p) return [];
        $s=$p[0]; $t=$p[1]; $x=$from%9; $y=intdiv($from,9); $out=[];
        $add=function($nx,$ny) use (&$out,$b,$s,$from) { if (self::inside($nx,$ny)) { $to=$ny*9+$nx; if (!$b[$to] || $b[$to][0]!==$s) $out[]=['from'=>$from,'to'=>$to]; } };
        if ($t==='r' || $t==='c') {
            foreach ([[1,0],[-1,0],[0,1],[0,-1]] as $d) {
                $screen=false;
                for ($nx=$x+$d[0],$ny=$y+$d[1];self::inside($nx,$ny);$nx+=$d[0],$ny+=$d[1]) {
                    $q=$b[$ny*9+$nx];
                    if ($t==='r') { if (!$q || $q[0]!==$s) $add($nx,$ny); if ($q) break; }
                    elseif (!$screen) { if (!$q) $add($nx,$ny); else $screen=true; }
                    elseif ($q) { if ($q[0]!==$s) $add($nx,$ny); break; }
                }
            }
        } elseif ($t==='h') {
            foreach ([[2,1],[2,-1],[-2,1],[-2,-1],[1,2],[-1,2],[1,-2],[-1,-2]] as $d) {
                $lx=$x+(abs($d[0])===2?($d[0]>0?1:-1):0); $ly=$y+(abs($d[1])===2?($d[1]>0?1:-1):0);
                if (self::inside($x+$d[0],$y+$d[1]) && !$b[$ly*9+$lx]) $add($x+$d[0],$y+$d[1]);
            }
        } elseif ($t==='e') {
            foreach ([[2,2],[2,-2],[-2,2],[-2,-2]] as $d) { $nx=$x+$d[0];$ny=$y+$d[1]; if (self::inside($nx,$ny) && ($s==='r'?$ny>=5:$ny<=4) && !$b[($y+intdiv($d[1],2))*9+$x+intdiv($d[0],2)]) $add($nx,$ny); }
        } elseif ($t==='a') {
            foreach ([[1,1],[1,-1],[-1,1],[-1,-1]] as $d) if (self::palace($x+$d[0],$y+$d[1],$s)) $add($x+$d[0],$y+$d[1]);
        } elseif ($t==='k') {
            foreach ([[1,0],[-1,0],[0,1],[0,-1]] as $d) if (self::palace($x+$d[0],$y+$d[1],$s)) $add($x+$d[0],$y+$d[1]);
            foreach ([-1,1] as $dy) for ($ny=$y+$dy;self::inside($x,$ny);$ny+=$dy) { $q=$b[$ny*9+$x]; if ($q) { if ($q===self::opposite($s).'k') $add($x,$ny); break; } }
        } elseif ($t==='p') {
            $add($x,$y+($s==='r'?-1:1)); if ($s==='r'?$y<=4:$y>=5) { $add($x-1,$y);$add($x+1,$y); }
        }
        return $out;
    }
    public static function check($b,$side) {
        $king=array_search($side.'k',$b,true); if ($king===false) return true;
        for ($i=0;$i<90;$i++) if ($b[$i] && $b[$i][0]===self::opposite($side)) foreach (self::pseudo($b,$i) as $m) if ($m['to']===$king) return true;
        return false;
    }
    public static function legal($b,$side) {
        $out=[]; for ($i=0;$i<90;$i++) if ($b[$i] && $b[$i][0]===$side) foreach (self::pseudo($b,$i) as $m) if (!self::check(self::apply($b,$i,$m['to']),$side)) $out[]=$m;
        return $out;
    }
    public static function outcome($b,$turn) {
        if (!in_array($turn.'k',$b,true)) return ['winner'=>self::opposite($turn),'reason'=>'将帅被吃'];
        if (!self::legal($b,$turn)) return ['winner'=>self::opposite($turn),'reason'=>self::check($b,$turn)?'将死':'困毙'];
        return null;
    }
    public static function moveText($b,$from,$to) {
        $p=$b[$from];$fx=$from%9;$fy=intdiv($from,9);$tx=$to%9;$ty=intdiv($to,9);
        $names=['r'=>['k'=>'帅','a'=>'仕','e'=>'相','h'=>'马','r'=>'车','c'=>'炮','p'=>'兵'],'b'=>['k'=>'将','a'=>'士','e'=>'象','h'=>'马','r'=>'车','c'=>'炮','p'=>'卒']];
        $red=['九','八','七','六','五','四','三','二','一'];$blue=['１','２','３','４','５','６','７','８','９'];$nums=$p[0]==='r'?$red:$blue;
        $action=$ty===$fy?'平':(($p[0]==='r'?$ty<$fy:$ty>$fy)?'进':'退');
        $end=$ty===$fy || in_array($p[1],['h','a','e'],true)?$nums[$tx]:($p[0]==='r'?['一','二','三','四','五','六','七','八','九'][abs($ty-$fy)-1]:(string)abs($ty-$fy));
        return $names[$p[0]][$p[1]].$nums[$fx].$action.$end;
    }
}
