<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class XiaoweihuiController extends Controller
{
    //
    public function index(){
        $res = DB::table('xiaoyouhui') -> paginate(15);
        foreach($res as $k => $vo){
            $vo -> schoolinfo =  DB::table('school') -> where([
                'id' => $vo -> school_id
            ]) -> first();
            $vo -> user_info = DB::table('user') -> where([
                'openid' => $vo -> add_user
            ]) -> first();
        }


        return view('admin/xiaoweihui/index') -> with([
            'res' => $res
        ]);

    }

    public function apiAddXiaoyou(Request $request){
        $id = $request -> input('id');
        if($id){
            //如果有id 带进来 更新
            $update_res = DB::table('xiaoyouhui') -> where([
                'id' => trim($id)
            ]) -> update([
                'name' => $request -> input('name'),
                'school_id' => $request -> input('school_id'),
                'area' => $request -> input('area'),
                'is_connect' => $request -> input('is_connect'),
                'guimo' => '',
                'content' => $request -> input('content'),
                'wx_name' => $request -> input('wx_name')
            ]);
            if($update_res){
                echo 'success';
            }else{
                echo 'error';
            }
            exit;

        }
        $id_res = DB::table('xiaoyouhui') -> insertGetId([
            'name' => $request -> input('name'),
            'school_id' => $request -> input('school_id'),
            'area' => $request -> input('area'),
            'is_connect' => $request -> input('is_connect'),
            'guimo' => '',
            'content' => $request -> input('content'),
            'wx_name' => $request -> input('wx_name'),
            'add_user' => $request -> input('add_user'),
            'created_at' => time()
        ]);


        //创建校友会的同时 在list中插一条记录
        DB::table('list') -> insert([
            'is_manage' => 1,
            'xiaoyou_id' => $id_res,
            'openid' => $request -> input('add_user'),
            'created_at' => time()
        ]);

        if($id_res){
            echo $id_res;
        }else{
            echo 'error';
        }
    }

    //通过校友会id 获取校友会详情
    public function getDetailById($id){
        $res = DB::table('xiaoyouhui') -> where([
            'id' => $id
        ]) -> first();

        $res -> school_info = DB::table('school') -> where([
            'id' => $res -> school_id
        ]) -> first();
        $res -> userinfo = DB::table('user') -> where([
            'openid' => $res -> add_user
        ]) -> first();
        $res -> activitys = DB::table('activity') -> where([
            'xiaoyou_id' => $res -> id
        ]) -> get();
        $weekarray=array("星期日","星期一","星期二","星期三","星期四","星期五","星期六");
        if(count($res -> activitys)){
            foreach($res -> activitys as $k =>$vo){
                $vo -> day = date('d',strtotime($vo -> date));
                $week = date('w',strtotime($vo -> date));
                $vo -> week = $weekarray[$week];
            }
        }
        $res -> number_xiaoyouhui = DB::table('list') -> where([
            'xiaoyou_id' => $res -> id
        ]) -> count();
        return response() -> json($res);
    }


    public function apiXiaoyouList(Request $request){
        $name = '';
        if($request -> input('keywords')){
            $name = $request -> input('keywords');
        }
        $openid = $request -> input('openid');
        //通过openid 查找他所属于的校友会
        $list_xiaoyou = DB::table('list') -> where([
            'openid' => $openid,
            'flag' => 0
        ]) -> get();

        foreach($list_xiaoyou as $k =>$vo){
            $list_xiaoyou[$k] -> info = DB::table('xiaoyouhui') -> where(function($query) use($vo,$name){
                $query -> where('id','=',$vo -> xiaoyou_id);
                if($name != ''){
                    $query -> where('name','like','%'.$name.'%');
                }
            }) -> get();
            if(count($list_xiaoyou[$k] -> info)){
                foreach($list_xiaoyou[$k] -> info as $v =>$oo){
                    $list_xiaoyou[$k] -> schoolinfo = DB::table('school') -> where(function($query) use($oo){
                        $query -> where('id','=',$oo -> school_id);
                    }) -> first();
                }
            }

            //如果有搜索名称 则搜索
            if(count($list_xiaoyou[$k] -> info)<=0){
                unset($list_xiaoyou[$k]);
                continue;
            }

            //每个里边有多少人
            $list_xiaoyou[$k] -> number = DB::table('list') -> where([
                'xiaoyou_id' => $vo -> xiaoyou_id
            ]) -> count();
            //最新活动
            $list_xiaoyou[$k] -> new_activity = DB::table('activity') -> where([
                'xiaoyou_id' => $vo -> xiaoyou_id
            ]) -> orderBy('id','desc') -> first();
            if($list_xiaoyou[$k] -> new_activity){
                //最新活动的参与人数
                $list_xiaoyou[$k] -> new_activity_number = DB::table('baoming')
                    -> where([
                        'huodong_id' => $list_xiaoyou[$k] -> new_activity -> id
                ]) -> count();
            }



        }
        $new_list = array();
        foreach($list_xiaoyou as $k =>$vo){
            Array_push($new_list,$vo);
        }
        return response() -> json($new_list);

    }

    //通过校友会id 返回通讯录
    public function apiXiaoyouDetail(Request $request){
        $id = $request -> input('id');
        $type = $request -> input('type');

        //$type 1 行业 2 专业 3 年级
        $res = DB::table('list') -> where([
            'xiaoyou_id' => $id
        ]) -> get();

        if($res){
            //得到全部的通讯录数据
            foreach($res as $k => $vo){
                $res[$k] -> userinfo  = DB::table('user') -> where([
                    'openid' => $vo -> openid
                ]) -> first();
            }
            //var_dump($res);
            //根据通讯录数据分类
            $newarr = [];
            foreach($res as $key => $val){

                switch ($type){
                    //按照行业分类
                    case 1:
                        $id_temp = $val -> userinfo -> hangye;
                        $key_temp = DB::table('setting')  -> where([
                            'id' => $id_temp
                        ]) -> first() -> name;
                    break;
                    //专业分类
                    case 2:
                        $id_temp = $val -> userinfo -> zhuanye_id;
                        $key_temp = DB::table('setting') -> where([
                            'id' => $id_temp
                        ]) -> first() -> name;
                    break;
                    //年级分类
                    case 3:
                        $key_temp = $val -> userinfo -> school_time . '级';
                    break;

                }
                //var_dump($type);exit;

                $newarr[$key_temp][] = $val;
                ksort($newarr);
            }
            //var_dump($newarr);exit;
            return response() -> json($newarr);
        }

    }
    public function searchXiaoyou(Request $request){
        $id = $request -> input('id');
        $keywords = $request -> input('keywords');
        $res = DB::table('list') -> where([
            'xiaoyou_id' => $id
        ]) -> get();
        if($res){
            //得到全部的通讯录数据
            foreach($res as $k => $vo){
                $res[$k] -> userinfo  = DB::table('user') -> where(function($query) use($vo,$keywords){
                    $query -> where('openid','=',$vo -> openid)
                            -> where('name','like','%'.$keywords.'%');
                }) -> first();
                if(!$res[$k] -> userinfo){
                    unset($res[$k]);
                    continue;
                }
            }
            return response() -> json($res);
        }
    }

    //删除校友会
    public function deleteXiaoyouhui($id){
        $res = DB::table('xiaoyouhui') -> where([
            'id' => $id
        ]) -> delete();
        $activity = DB::table('activity')->where('xiaoyou_id', $id)->pluck('id');
        DB::table('baoming') ->whereIn('huodong_id', $activity)-> delete();
        DB::table('activity') -> where([
            'xiaoyou_id' => $id
        ]) -> delete();
        DB::table('list') -> where([
            'xiaoyou_id' => $id
        ]) -> delete();
        echo 'success';
    }

    //加入校友会
    public function apiEnterXiaoyou(Request $request){
        //先看他是否已经加入
        if($request -> input('openid') && $request -> input('xiaoyou_id')){
            $isset = DB::table('list') -> where([
                'openid' => $request -> input('openid'),
                'xiaoyou_id' => $request -> input('xiaoyou_id'),
            ]) -> first();
            if($isset){
                echo 'error';
            }else{
                DB::table('list') -> insert([
                    'openid' => $request -> input('openid'),
                    'xiaoyou_id' => $request -> input('xiaoyou_id'),
                    'created_at' => time()
                ]);
            }
        }else{
            echo 'error';
        }
    }




}
