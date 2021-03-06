<?php
namespace app\api\model;

use think\Model;
use think\Log;
use think\Cache;
use think\Verify;
use think\config;
use think\db;

class Agent extends Model
{
    /**
     * 代理登录接口
     * @param $data 请求数组
     * @return string 返回参数值类型为json
     */
    public function login($data)
    {
            Log::info('调用登录接口——请求开始');
        /*     //检测数据
         $response = testing(['username','password','captcha']);
         $response = json_decode($response);
         if ($response->result_code != 200) {
         return $response;
         } */
        /*     //校验验证码
         if(!captcha_check($data['captcha'],'login')){
         //验证失败
         return return_json(2,'验证码输入错误');
         }; */
        if(!array_key_exists('openid',$data))
        {
            return  return_json(2,'代理账号不能为空');
        }

        $find['openid'] = $data['openid'];
    
        //查询数据
        $response = $this->where($find)->find();
         $response['wx_name'] = base64_decode($response['wx_name']);
        if(!$response) {
            return return_json(2,'账号或者密码有误,请重试');
        }
        if($response['pid']===0 && $response['account'] != '' )
        {
            return return_json(2,'账号或者密码有误,请重试');
        }
        if($response['account'] != ''){
            $pidname = $this->where(['id'=>$response['pid']])->field('account')->find();
            $response['pname'] = $pidname['account'];
        }
    
        if ($response)
        {
            if($response['status'] == 2)
            {
                return return_json(2,'账号异常已被禁用');
            }
            Log::info('调用登录接口——查询成功');
            $insert['agent_id'] = $response['id'];
            $insert['login_time'] = time();
            $insert['operation'] = '用户登录';
            $result = db('agent_log')->insert($insert);
            if (!$result) {
                return return_json(2,'账号或者密码有误,请重试');
            }
            //缓存token
            /*       $token = md5(time().'hand_game');
             $res = $this->where($find)->update(['token'=>$token]);
             Cache::set('user_'.$response['id'],'123456',1800); */
            return return_json(1,'登录成功',$response);
        } else {
            Log::info('调用登录接口——查询失败');
            return return_json(2,'账号或者密码有误,请重试');
        }
    }
    /**
     * 创建代理
     * @param unknown $data
     * @return string
     */
    public function  agentcreated($data)
    {
        //获取id
        if(!array_key_exists('id', $data))
        {
            return  return_json(2,'请输入正确的微信号');
        } else {
            $where['id'] = $data['id'];
        }
   
        if(!array_key_exists('phone', $data))
        {
            return  return_json(2,'电话不能为空');
        } else {
            $insert['phone'] = $data['phone'];
            $insert['account'] = $data['phone'];
        }
        if(!array_key_exists('rname', $data))
        {
            return  return_json(2,'真实姓名不能为空');
        } else {
            $insert['rname'] = $data['rname'];
        }
        if(!array_key_exists('pid', $data))
        {
            return  return_json(2,'推荐码不能为空');
        } else {
            $insert['pid'] = $data['pid'];
            $result = $this->where(['id'=>$insert['pid']])->find();
            if(!$result) {
                return  return_json(2,'推广员不存在');
            }
        }
        $insert['status'] = 3;
        //检测看是否已经修改
        $result = $this->where($where)->update($insert);
        if(!$result){
            return  return_json(2,'申请失败');
        }
        return  return_json(1,'申请成功');

    }
	/**
	*代理信息查询
	*/
    public function  getAgentInfo($data)
    {
        //获取id
        if(!array_key_exists('id', $data))
        {
            return  return_json(2,'请输入代理编号');
        } else {
            $where['id'] = $data['id'];
        }
        $res = db('agent')->where(['id'=>$data['id']])->find();
		if(!$res) {
			return  return_json(2,'代理不存在');
		}
		$result = db('agent')->where(['id'=>$res['pid']])->find();
	    if(!$res) {
			$res['pname'] = '0';
		}else{
			$res['pname'] = $result['account'];
		}
		
		$ress = db('agent_card')->where(['agent_id'=>$res['id']])->order('created_at desc')->limit(1)->select();
	    if(!$ress) {
			$res['last_send_card'] = '0';
		}else{
			$res['last_send_card'] = $ress[0]['created_at'];
		}

		 $resss = db('plat_card')->where(['agent_id'=>$res['id']])->order('created_at desc')->limit(1)->select();
		    if(!$resss) {
			$res['last_buy_card'] = '0';
		}else{
			$res['last_buy_card'] = $resss[0]['created_at'];
		}
		
		$count = db('agent')->where(['pid'=>$data['id']])->count();
				    if(!$count) {
			$res['child_count'] = '0';
		}else{
			$res['child_count'] = $count;
		}
		$res['wx_name'] = base64_decode($res['wx_name']);
		return return_json(1,'平台发卡记录',$res,[]);
    }
    
    /**
     * 待审审核代理列表
     * @param unknown $data
     * @return string
     */
    public function checkagentlist($data)
    {
        //分页
        $where = ' where  a.status = 3 and a.pid = b.id';//注意下面计算页数的sql
        //计算总页数
        $sqlc =  "select count(id)  from hand_agent where status = 3 ";
        $count = db()->Query($sqlc);
        $totle = $count[0]["count(id)"];//总数
        if(!array_key_exists('limit_page', $data))
        {
            $limit = 15;
        } else {
            $limit = $data['limit_page'];
        }
        //$limit = 15;//每页条数
        $pageNum = ceil ( $totle/$limit); //总页数
        //当前页
        if(array_key_exists('npage', $data))
        {
            $npage = $data['npage'];
        }else{
            $npage = 1;
        }
        $start = ($npage-1)*$limit;
        $page = [];
        $page['npage'] = $npage;//当前页
        $page['totle'] = $totle;//总条数
        $page['tpage'] = $pageNum;//总页数
        //开始数$start $limie
        $sql =  "select a.id,a.wx_name,a.rname,a.phone,a.created_at,b.account as p_account from  hand_agent as a , hand_agent b ".$where."  limit ".$start.",".$limit;
    
        $res = db()->Query($sql);
		
		foreach($res as $key => $val) {
			$res[$key]['wx_name'] = base64_decode($val['wx_name']);
		}
        if(!$res)
        {
            return return_json(1,'暂无信息 ');
        }
        //返回结果
        return return_json(1,'平台发卡记录',$res,$page);
    }
    /**
     * 完成平台对代理的审核
     * @param unknown $data
     * @return string
     */
    public function  checkagent($data)
    {
        //获取id
        if(!array_key_exists('id', $data))
        {
            return  return_json(2,'该用新增代理异常');
        } else {
            $where['id'] = $data['id'];
        }
        //获取状态 状态1为审核通过 status修改为1  状态1为审核不通过 status修改为3
        if(!array_key_exists('status', $data))
        {
            return  return_json(2,'新增代理状态异常');
        } else {
            if($data['status'] == 1 ) {
                $update['status'] = 1;
            } else {
                $update['status'] = 5;
            }
        }
        //获取拒绝原因
        if($update['status'] == 5){
            if(!array_key_exists('id', $data))
            {
                return  return_json(2,'该用新增代理异常');
            } else {
                $update['reject_cause'] =  $data['reject_cause'];
            }
        }
        //检测看是否已经修改
        $result = $this->where(['status' => 1,'id' => $where['id']])->find();
        if($result){
            return  return_json(2,'代理已审核');
        }
        //执行更新数据库
        $res = $this->where($where)->update($update);
        if($res) {
            return  return_json(1,'操作成功');
        } else {
            return  return_json(2,'操作失败');
        }
    }

    /**
     *
     * @param $data
     * @return string
     */
    public function getstatus($data)
    {
        if(!array_key_exists('id', $data))
        {
            return  return_json(2,'该用新增代理异常');
        } else {
            $where['id'] = $data['id'];
        }
        $result = $this->where(['id' => $where['id']])->find();
        return return_json(1,'平台发卡记录',$result);
    }

    /**
     * 微信登陆
     * @param $data
     * @return string
     */
    public function wxLogin($data)
    {
        $result = $this->where(['openid' => $data['openid']])->find();

        if($result) {
            if($result['status'] == 4) {
                return 'error';
            }
            $where['openid'] = $data['openid'];
            if($data['img_url'] != $result['img_url'] || base64_encode($data['wx_name']) != $result['wx_name']) {
                $childnum = db('agent')->where(['pid'=>$result['id']])->count();
                $update['child_num'] = $childnum;
                $update['access_token'] = $data['access_token'];
                $update['update_at'] = time();
                $update['img_url'] = $data['img_url'];
                $update['wx_name'] = base64_encode($data['wx_name']);
                $res = $this->where($where)->update($update);
                if($res){
                    return 'ok';
                }
               return 'error';
            }
            $childnum = db('agent')->where(['pid'=>$result['id']])->count();	
            $update['child_num'] = $childnum;	
            $update['access_token'] = $data['access_token'];   
            $update['update_at'] = time();			
            $res = $this->where($where)->update($update);
            if($res){
                return 'ok';
            }
            return 'error';
        } else {		
            $insert['openid'] = $data['openid'];
			if($result != NULL) {
				$insert['pid']  = $data['pid'];
			}
            $insert['access_token'] = $data['access_token'];
            $insert['img_url'] = $data['img_url'];
            $insert['wx_name'] = base64_encode($data['wx_name']);
			$insert['created_at'] = time();
            $res = $this->insertGetId($insert);

            //qr start
            vendor('topthink.think-image.src.Image');
            vendor('phpqrcode.phpqrcode');

            $qr_code_path = './upload/qr_code/';

            if (!file_exists($qr_code_path)) {
                mkdir($qr_code_path,0777,true);

            }
            /* 生成二维码*/
            //include 'phpqrcode.php';    http://www.baiyaomall.com/mobile/User/reg.html
            $value = 'http://agency.daque.com/agencyAdmin/index.html#/sureLogin?id='.$res; //二维码内容   http://www.xzljszm.top/#/register?code=by888888

            $errorCorrectionLevel = 'L';//容错级别
            $matrixPointSize = 6;//生成图片大小
            $qr_code_file = $qr_code_path.time().rand(1, 10000).'.png';

            //生成二维码图片
            \QRcode::png($value, $qr_code_file, $errorCorrectionLevel, $matrixPointSize, 2);

            $logo =Config::get('base_url').'/images/image_icon.jpg';//准备好的logo图片
            $QR = $qr_code_file;//已经生成的原始二维码图
            if ($logo !== FALSE) {
                $QR = imagecreatefromstring(file_get_contents($QR));
                $logo = imagecreatefromstring(file_get_contents($logo));
                $QR_width = imagesx($QR);//二维码图片宽度
                $QR_height = imagesy($QR);//二维码图片高度
                $logo_width = imagesx($logo);//logo图片宽度
                $logo_height = imagesy($logo);//logo图片高度
                $logo_qr_width = $QR_width / 5;
                $scale = $logo_width/$logo_qr_width;
                $logo_qr_height = $logo_height/$scale;
                $from_width = ($QR_width - $logo_qr_width) / 2;
                //重新组合图片并调整大小
                imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,
                    $logo_qr_height, $logo_width, $logo_height);
            }
            //qr  end
            $update1['code_url'] = Config::get('base_url').$qr_code_file;
            $res = $this->where(['id'=>$res])->update($update1);
            if($res){
                return 'ok';
            }
            return 'error';
        }
    }
    /**
     * 生成二维码
     * @param $id
     * @return string
     */
    public function qr_code($id){

        vendor('topthink.think-image.src.Image');
        vendor('phpqrcode.phpqrcode');
        $qr_code_path = './upload/qr_code/';

        if (!file_exists($qr_code_path)) {
            mkdir($qr_code_path);
        }

        /* 生成二维码*/
        //include 'phpqrcode.php';    http://www.baiyaomall.com/mobile/User/reg.html
        $value = 'http://agency.daque.com/agencyAdmin/index.html#/sureLogin?id='.$id; //二维码内容   http://www.xzljszm.top/#/register?code=by888888
        $errorCorrectionLevel = 'L';//容错级别
        $matrixPointSize = 6;//生成图片大小
        $qr_code_file = $qr_code_path.time().rand(1, 10000).'.png';
        //生成二维码图片
        \QRcode::png($value, $qr_code_file, $errorCorrectionLevel, $matrixPointSize, 2);
        $logo =Config::get('base_url').'images/image_icon.jpg';//准备好的logo图片
        $QR = $qr_code_file;//已经生成的原始二维码图
        if ($logo !== FALSE) {
            $QR = imagecreatefromstring(file_get_contents($QR));
            $logo = imagecreatefromstring(file_get_contents($logo));
            $QR_width = imagesx($QR);//二维码图片宽度
            $QR_height = imagesy($QR);//二维码图片高度
            $logo_width = imagesx($logo);//logo图片宽度
            $logo_height = imagesy($logo);//logo图片高度
            $logo_qr_width = $QR_width / 5;
            $scale = $logo_width/$logo_qr_width;
            $logo_qr_height = $logo_height/$scale;
            $from_width = ($QR_width - $logo_qr_width) / 2;
            //重新组合图片并调整大小
            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,
                $logo_qr_height, $logo_width, $logo_height);
        }
        $url = Config::get('base_url').$qr_code_file;
        return $url;
    }
    /***
     * 返利信息
     */
    public function refee()
    {
        $result = db('refeeset')->where(['id'=>1])->find();
        return return_json(1,'返利信息',$result,[]);
    }
    //返利费用
    public function agentreturnfee($data){
        if(!array_key_exists('id', $data))
        {
            return  return_json(2,'该用新增代理异常');
        } else {
            $where['id'] = $data['id'];
        }
        $result = db('agent')->field('return_fee')->where($where)->find();
        return return_json(1,'返利信息',$result,[]);
    }
    /**
     * 返利信息设置
     * @param $data
     * @return string
     */
    public function refeeset($data)
    {
        $update = [];
        if(array_key_exists('one_fee', $data))
        {
            $update['one_fee'] = $data['one_fee'];
        } 
        if(array_key_exists('tow_fee', $data))
        {
            $update['tow_fee'] = $data['tow_fee'];
        } 
        if(array_key_exists('three_fee', $data))
        {
            $update['three_fee'] = $data['three_fee'];
        } 
        if(count($update) == 0) {
            return  return_json(1,'数据重复');
        }
        $result = db('refeeset')->where(['id'=>1])->update($update);
        if($result) {
            $result1 = db('refeeset')->where(['id'=>1])->find();
            return  return_json(1,'已经设置',$result1);
        }
        return  return_json(1,'不能重复操作');

    }
    /**
     * 提现审核列表
     * @param $data
     * @return string
     */
    public function returnfeelist($data)
    {
		        //分页
       if(!array_key_exists('id', $data))
        {
            return  return_json(2,'该用新增代理异常');
        } else {
            $where['id'] = $data['id'];
        }
		    $where = ' where pid =  '.$data['id'];//注意下面计算页数的sql
		    if(array_key_exists('account',$data) && $data['account'] !='')
            {
                $where .= ' and account like  "%'.$data["account"].'%"';
            }
            if(array_key_exists('start_time', $data) && !array_key_exists('end_time', $data) && $data['start_time'] !='' && $data['end_time'] !='')
            {
                $where .= ' and created_at >= '.$data['start_time'];
            }
            if(!array_key_exists('start_time', $data) && array_key_exists('end_time', $data) && $data['start_time'] !='')
            {
                $where .= ' and  created_at <= '.$data['end_time'];
            }
            if(array_key_exists('start_time', $data) && array_key_exists('end_time', $data)&& $data['end_time'] !='')
            {
                $where .= ' and  created_at >= '.$data['start_time'].' and  created_at <= '.$data['end_time'];
            } 

        //计算总页数
        $sqlc =  "select count(id)  from hand_return_fee_log ".$where;
        $count = db()->Query($sqlc);
        $totle = $count[0]["count(id)"];//总数
        if(!array_key_exists('limit_page', $data))
        {
            $limit = 15;
        } else {
            $limit = $data['limit_page'];
        }
        //$limit = 15;//每页条数
        $pageNum = ceil ( $totle/$limit); //总页数
        //当前页
        if(array_key_exists('npage', $data))
        {
            $npage = $data['npage'];
        }else{
            $npage = 1;
        }
        $start = ($npage-1)*$limit;
        $page = [];
        $page['npage'] = $npage;//当前页
        $page['totle'] = $totle;//总条数
        $page['tpage'] = $pageNum;//总页数
	    $sql =  "select * from hand_return_fee_log ".$where."  limit ".$start.",".$limit;
        $res = db()->Query($sql);
        if(!$res) {
            return return_json(1,'没有数据',[],$page);
        } else {
            return return_json(1,'审核列表',$res,$page);
        }
    }
	    /**
     * 提现审核列表
     * @param $data
     * @return string
     */
    public function feelist($data)
    {
		        //分页
       /*if(!array_key_exists('id', $data))
        {
            return  return_json(2,'该用新增代理异常');
        } else {
            $where['id'] = $data['id'];
        }*/
		    $where = ' where status =  1';//注意下面计算页数的sql
		    if(array_key_exists('account',$data) && $data['account'] !='')
            {
                $where .= ' and account like  "%'.$data["account"].'%"';
            }
            if(array_key_exists('start_time', $data) && !array_key_exists('end_time', $data) && $data['start_time'] !='' && $data['end_time'] !='')
            {
                $where .= ' and created_at >= '.$data['start_time'];
            }
            if(!array_key_exists('start_time', $data) && array_key_exists('end_time', $data) && $data['start_time'] !='')
            {
                $where .= ' and  created_at <= '.$data['end_time'];
            }
            if(array_key_exists('start_time', $data) && array_key_exists('end_time', $data)&& $data['end_time'] !='')
            {
                $where .= ' and  created_at >= '.$data['start_time'].' and  created_at <= '.$data['end_time'];
            } 

        //计算总页数
        $sqlc =  "select count(id)  from hand_return_fee ".$where;
        $count = db()->Query($sqlc);
        $totle = $count[0]["count(id)"];//总数
        if(!array_key_exists('limit_page', $data))
        {
            $limit = 15;
        } else {
            $limit = $data['limit_page'];
        }
        //$limit = 15;//每页条数
        $pageNum = ceil ( $totle/$limit); //总页数
        //当前页
        if(array_key_exists('npage', $data))
        {
            $npage = $data['npage'];
        }else{
            $npage = 1;
        }
        $start = ($npage-1)*$limit;
        $page = [];
        $page['npage'] = $npage;//当前页
        $page['totle'] = $totle;//总条数
        $page['tpage'] = $pageNum;//总页数
	    $sql =  "select * from hand_return_fee ".$where."  limit ".$start.",".$limit;
        $res = db()->Query($sql);
        if(!$res) {
            return return_json(1,'没有数据',[],$page);
        } else {
            return return_json(1,'审核列表',$res,$page);
        }
    }
	    /**
     * 提现审核
     * @param $data
     * @return string
     */
    public function platreturn($data)
    {
			
        if(!array_key_exists('id', $data))
        {
            return  return_json(2,'记录不存在');
        } else {
            $update['agent_id'] = $data['id'];
        }
        if(!array_key_exists('fee_num', $data))
        {
            return  return_json(2,'提现数目');
        } else {
            $update['fee_num'] = $data['fee_num'];
        }
		//判断余额是否足够 
			$where['id'] = $data['id'];
			$agentInfo = $this->where($where)->select();
			if($agentInfo[0]['return_fee'] < $update['fee_num']){
				 return  return_json(2,'余额不足');
			}
		
            if(!array_key_exists('phone', $data))
            {
                return  return_json(2,'电话不能为空');
            } else {
                $update['phone'] = $data['phone'];
            }

            if(!array_key_exists('get_account', $data))
            {
                return  return_json(2,'获取帐号有误');
            } else {
                $update['get_account'] = $data['get_account'];
            }
			if(!array_key_exists('pay_type', $data))
            {
                return  return_json(2,'支付方式');
            } else {
                $update['pay_type'] = $data['pay_type'];
            }
						if(!array_key_exists('rname', $data))
            {
                return  return_json(2,'真名不能为空');
            } else {
                $update['rname'] = $data['rname'];
            }
			$update['created_at'] = time();
			//修改余额2
			
			$update2['return_fee'] = $agentInfo[0]['return_fee'] - $update['fee_num'];
			$agentInfo = $this->where($where)->update($update2);

        $result = db('return_fee')->insert($update);
        if($result) {
             //$result1 = db('return_fee')->where($where)->find();
            return return_json(1,'操作成功',$result,[]);
        }
        return return_json(1,'操作失败',$result,[]);

    }
    
    /**
     * 提现审核
     * @param $data
     * @return string
     */
    public function returnfee($data)
    {
        if(!array_key_exists('id', $data))
        {
            return  return_json(2,'记录不存在');
        } else {
            $where['id'] = $data['id'];
        }
        if(!array_key_exists('plat_id', $data))
        {
            return  return_json(2,'操作人不存在');
        } else {
            $update['plat_id'] = $data['plat_id'];
        }
        if(!array_key_exists('status', $data))
        {
            return  return_json(2,'状态异常');
        } else {
            $update['status'] = $data['status'];
        }
        if($data['status'] == 3) {
            if(!array_key_exists('cause', $data))
            {
                return  return_json(2,'缺少失败原因');
            } else {
                $update['cause'] = $data['cause'];
            }
			//huiti
			  $result1 = db('return_fee')->where($where)->find();
			  $agentInfo = db('agent')->where(['id'=>$result1['agent_id']])->find();
			  $hht['return_fee'] = $agentInfo['return_fee'] + $result1['fee_num'];
			  db('agent')->where(['id'=>$result1['agent_id']])->update($hht);
        }
        $result = db('return_fee')->where($where)->update($update);
        if($result) {
            $result1 = db('return_fee')->where($where)->find();
            return return_json(1,'操作成功',$result1,[]);
        }
        return return_json(1,'操作失败',$result,[]);

    }

    /**
     * 新增代理
     *
     */
    public function paltcreated($data)
    {
        //系统日志

        //字段验证
        if(array_key_exists('account',$data) )
        {
            if($data['account'] == '') {
                return  return_json(2,'新增管理帐号不能为空');
            }

        }else{
            return  return_json(2,'新增管理帐号不能为空');
        }
        if(array_key_exists('password',$data) )
        {
            if($data['account'] == '') {
                return  return_json(2,'新增管理密码不能为空');
            }

        }else{
            return  return_json(2,'新增管理密码不能为空');
        }




        //参数验证
        $insert['account'] = $data['account'];

        //防止重复
        $find = $this->where($insert)->find();
        if($find)
        {
            return return_json(2,'账号已存在');
        }
        $insert['password']= md5($data['password']);

       //执行添加
        $insert['status'] = 1;
        $insert['created_at'] = time();
        $res = $this->insert($insert);
        if(!$res)
        {
            return  return_json(2,'创建失败');
        }else{
            unset($insert['password']);
            $res = $this->where($insert)->find();
            return return_json(1,'创建成功',[]);
        }
    }
	/**************************************************************************代理 ************************************************************************************/


	public function agentlist($data)
	{
		//分页
		$where = ' where  a.status = 1  and a.pid = b.id';//注意下面计算页数的sql
		//计算总页数
		$sqlc =  "select count(id)  from hand_agent where status = 1 ";
		$count = db()->Query($sqlc);
		$totle = $count[0]["count(id)"];//总数
		$limit = 15;//每页条数
		$pageNum = ceil ( $totle/$limit); //总页数
		//当前页
		if(array_key_exists('npage', $data))
		{
			$npage = $data['npage'];
		}else{
			$npage = 1;
		}
		$start = ($npage-1)*$limit;
		$page = [];
		$page['npage'] = $npage;//当前页
		$page['totle'] = $totle;//总条数
		$page['tpage'] = $pageNum;//总页数
		//开始数$start $limie
		$sql =  "select a.id,a.account,a.rname,a.pid,a.phone,a.card_num,a.child_num,a.rebate,a.created_at,b.account as p_account from  hand_agent as a , hand_agent as b ".$where."  limit ".$start.",".$limit;
		//获取信息列表
		$res = db()->Query($sql);
		
		
		if(!$res)
		{
			return return_json(1,'暂无信息 ');
		}
		//获取列表相关信息
		foreach($res as $key => $val) {
			$res[$key]['wx_name'] = base64_decode($val['wx_name']);
			$where1['agent_id'] = $val['id'];
			$res = db('agent_card')->where($where1)->order('created_at desc')->limit(1)->find();//最后进卡
			$res = db('agent_card')->where($where1)->order('created_at desc')->limit(1)->find();//最后发卡
			$res = db('agent_card')->where($where1)->order('created_at desc')->limit(1)->find();//最后登陆
		}
		//返回结果
		return return_json(1,'平台发卡记录',$res,$page);	
	}
	/****************************************************************************之前的接口************************************************************************************/
    /**
     * 新增代理
     *  
     */
    public function created_agent($data)
    {
        //系统日志
        return 'ok';
        //字段验证
        if(!array_key_exists('account',$data))
        {
            return  return_json(2,'新增代理账号不能为空');
        }
        if(!array_key_exists('phone',$data))
        {
            return  return_json(2,'新增代理手机号不能为空');
        }else{
            $mobile = is_mobile($data['phone']);
            if(!$mobile){
                return return_json(2,'请输入正确手机号');
            }
        }
        if(!array_key_exists('wx_name',$data))
        {
            return  return_json(2,'新增代理微信号不能为空');
        }
        if(!array_key_exists('rname',$data))
        {
            return  return_json(2,'新增代理真实姓名不能为空');
        }
        if(!array_key_exists('password',$data))
        {
            return  return_json(2,'新增代理密码不能为空');
        }
        if(!array_key_exists('pid',$data))
        {
            return  return_json(2,'新增代理未定义');
        }
        
        //参数验证       
        $insert['account'] = $data['account'];

        //防止重复
        $find = $this->where($insert)->find();
        if($find)
        {
           return return_json(2,'账号已存在');
        }
        $insert['password']= md5($data['password']);
        $insert['phone'] = $data['phone'];
        $insert['wx_name'] = $data['wx_name'];
        $insert['rname'] = $data['rname'];
        //执行添加
        $insert['pid']     = $data['pid'];
        $insert['created_at'] = time();
        $res = $this->insert($insert);
        if(!$res)
        {
           return  return_json(2,'创建失败');
        }else{
            unset($insert['password']);
            $res = $this->where($insert)->find();
           return return_json(1,'创建成功',$res);
        }
    }
    /**
     * 代理下代理列表 param $type = 1  平台下的代理列表
     * @param unknown $data
     * @return string
     */
    public function agentCdList($data)
    {
        if(!array_key_exists('pid', $data)){
            return  return_json(2,'代理信息异常，请联系客服');
        }
        if(array_key_exists('account',$data) && $data['account'] !='')
        {
            $where = 'where  account like  "%'.$data["account"].'%"  and  pid = '.$data['pid'];
        }else{
            $where = 'where pid ='.$data['pid'];
        }
       
        //分页
        //计算总页数
        $sqlc =  "select count(id)  from hand_agent ".$where;  
        $count = db()->Query($sqlc);      
        $totle = $count[0]["count(id)"];//总数
        $limit = 15;//每页条数
        $pageNum = ceil ( $totle/$limit); //总页数
        //当前页
        if(array_key_exists('npage', $data))
        {
            $npage = $data['npage'];
        }else{
            $npage = 1;
        }
        $start = ($npage-1)*$limit;
        $page = [];
        $page['npage'] = $npage;//当前页
        $page['totle'] = $totle;//总条数
        $page['tpage'] = $pageNum;//总页数
        //开始数$start $limie
        $sql =  "select * from  hand_agent ".$where."  limit ".$start.",".$limit;
  
        $res = db()->Query($sql);
		foreach($res as $key =>$val) {
			$res[$key]['wx_name'] = $val['wx_name'];
		}
        if(!$res)
        {
            return return_json(1,'暂无信息 ');
        }
        //返回结果
        return return_json(1,'平台发卡记录',$res,$page);
    }
    /**
     * 平台下代理列表 param $type = 1  平台下的代理列表
     * @param unknown $data
     * @return string
     */
    public function agentList1($data)
    {
        if(array_key_exists('account',$data) && $data['account'] !='')
        {
            $where = 'where  account like  "%'.$data["account"].'%" and where pid > 0';
        }else{
            $where = 'where pid > 0';
        }
         
        //分页
        //计算总页数
        $sqlc =  "select count(id)  from hand_agent ".$where;
    
    
        $count = db()->Query($sqlc);
        $totle = $count[0]["count(id)"];//总数
        $limit = 15;//每页条数
        $pageNum = ceil ( $totle/$limit); //总页数
        //当前页
        if(array_key_exists('npage', $data))
        {
            $npage = $data['npage'];
        }else{
            $npage = 1;
        }
        $start = ($npage-1)*$limit;
        $page = [];
        $page['npage'] = $npage;//当前页
        $page['totle'] = $totle;//总条数
        $page['tpage'] = $pageNum;//总页数
        //开始数$start $limie
        $sql =  "select * from  hand_agent ".$where." limit ".$start.",".$limit;
        $res = db()->Query($sql);
        if(!$res)
        {
            return return_json(1,'暂无信息 ');
        }
        //返回结果
        return return_json(1,'平台发卡记录',$res,$page);
    }


    /**
     * 平台房卡数
     */
    public function agent_card_num($data)
    {
        if(!array_key_exists('id',$data))
        {
            return  return_json(2,'代理账号不存在');
        }
    
        $where['id'] = $data['id'];
        $find = $this->field('card_num')->where($where)->find();
        if(!$find)
        {
            return return_json(2,'账号信息有误');
        }
        return return_json(1,'代理房卡',$find);
    }
    /**
     * 平台房卡数
     */
    public function cardInfo($data)
    {
        if(!array_key_exists('id',$data))
        {
            return  return_json(2,'代理账号不存在');
        }
    
        $where['id'] = $data['id'];
        $find = $this->field('card_num')->where($where)->find();
        if(!$find)
        {
            return return_json(2,'账号信息有误');
        }
        return return_json(1,'代理房卡',$find);
    }
    /**
     * 平台退出
     */
    public function plat_loginout($data)
    {
        if(!array_key_exists('account',$data))
        {
            return  return_json(2,'代理账号不能为空');
        }

        $where['account'] = $data['account'];
        $find = $this->where($where)->find();
        if(!$find)
        {
            return return_json(2,'账号信息有误');
        }
        return return_json(1,'成功退出');                
    }
    public  function agent_change($data)
    {
        //字段检验  id account password
        if(!array_key_exists('pid',$data))
        {
            return  return_json(2,'代理账号不能为空');
        }else{
			
		}
        //参数检验 
        if(!array_key_exists('account',$data))
        {
            return  return_json(2,'代理账号不能为空');
        }
        if(!array_key_exists('opassword',$data))
        {
            return  return_json(2,'旧密码不能为空');
        }
        if(!array_key_exists('password',$data))
        {
            return  return_json(2,'代理密码不能为空');
        }
        if(!array_key_exists('id',$data))
        {
            return  return_json(2,'代理不存在');
        }
        $where['password'] =  md5($data['opassword']); 
        $where['account'] =  $data['account'];  
        $update['password'] = md5($data['password']);
        unset($data['password']);
        //检查账号是否存在
        $find = $this->where($where)->find();
        if($find['password']==$update['password'])
        {
            return return_json(2,'修改密码相同');
        }
        //执行修改
        $response  = $this->where(['id'=>$data['id']])->update($update);
        if(!$response)
        {
            return return_json(2,'修改失败');
        }
        //返回结果 
        $return = $this->where($where)->find();
        return return_json(1,'修改成功',$return);
        
    }
    /**
     * 平台登录接口
     * @param $data 请求数组
     * @return string 返回参数值类型为json
     */
    public function login_plat($data)
    {
        Log::info('调用登录接口——请求开始');
        /*     //检测数据
         $response = testing(['username','password','captcha']);
         $response = json_decode($response);
         if ($response->result_code != 200) {
         return $response;
         } */
        /*     //校验验证码
         if(!captcha_check($data['captcha'],'login')){
         //验证失败
         return return_json(2,'验证码输入错误');
         }; */
        //数据组装
        if(!array_key_exists('account',$data))
        {
            return  return_json(2,'代理账号不能为空');
        }
        if(!array_key_exists('password',$data))
        {
            return  return_json(2,'代理密码不能为空');
        }
        Log::info('调用登录接口——数据组装');
        $find['account'] = $data['account'];
        $find['password'] = md5($data['password']);
        $find['status']   = 1;
        //查询数据
        $response = $this->where($find)->field('id,pid,account,card_num,token')->find();
        if($response['pid'] !=0)
        {
            return return_json(2,'非平台账号');
        }
        if ($response) {
            Log::info('调用登录接口——查询成功');
            $insert['agent_id'] = $response['id'];
            $insert['login_time'] = time();
            $insert['operation'] = '用户登录';
            $result = db('agent_log')->insert($insert);
            if (!$result) {
                return return_json(2,'账号或者密码有误,请重试');
            }
            return return_json(1,'登录成功',$response);
        } else {
            Log::info('调用登录接口——查询失败');
            return return_json(2,'账号或者密码有误,请重试');
        }
    }
    /**
     * 代理商列表信息修改
     */
    public function agentInfoChange($data)
    {
        if(!array_key_exists('id',$data))
        {
            return  return_json(2,'代理账号不能为空');
        }
        if(array_key_exists('rname',$data))
        {
           $updata['rname'] = $data['rname'];
        }
         if(array_key_exists('wx_name',$data))
        {
           $updata['wx_name'] = $data['wx_name'];
        }
        if(array_key_exists('phone',$data))
        {
            $updata['phone'] = $data['phone'];
        }
		if(array_key_exists('pid',$data))
        {
            $updata['pid'] = $data['pid'];
            $res1 = $this->where(['id'=>$data['id']])->find();
            if($res1['pid'] == $updata['pid'] ){
                return return_json(2,'非法层级关系，不能做为父系上级');
            }
            $res2 = $this->where(['pid'=>$res1['pid']])->find();
            if($res2['pid'] == $updata['pid'] ){
                return return_json(2,'非法层级关系，不能做为父系上级');
            }
        }
		$updata['update_at'] = time();
        $res = $this->where(['id'=>$data['id']])->update($updata);
        if(array_key_exists('pid',$data)) {
        }
        if (!$res && $res['status']!=1) {
            return return_json(2,'数据一样，请更改');
        }else{
            $result = $this->where(['id'=>$data['id']])->find();
        }
        return return_json(1,'更新成功',$result);
    }
    /**
     * 代理商列表信息修改
     */
    public function agentStatus($data)
    {
        if(!array_key_exists('id',$data))
        {
            return  return_json(2,'代理账号不能为空');
        }
        if(!array_key_exists('status',$data))
        {
            return  return_json(2,'代理状态异常');
        }else{
            $updata['status'] = $data['status'];
        }
        $res = $this->where(['id'=>$data['id']])->update($updata);
        if (!$res && $res['status']!=1) {
            return return_json(2,'代理账号异常已被禁用');
        }else{
            $result = $this->where(['id'=>$data['id']])->find();
        }
        return return_json(1,'更新成功',$result);
    }    
    /**
     * 代理商列表信息修改
     */
    public function agentInfo($data)
    {
        if(!array_key_exists('id',$data))
        {
            return  return_json(2,'代理账号不能为空');
        }

        $result = $this->where(['id'=>$data['id']])->find();
        if (!$result && $result['status']!=1) {
            return return_json(2,'代理账号异常已被禁用');
        }
		$result['wx_name'] = base64_decode($result['wx_name']);
        return return_json(1,'更新成功',$result);
    }
    /**
     * 代理商列表信息修改
     */
    public function agentAcInfo($data)
    {
    	if(!array_key_exists('account',$data))
    	{
    		return  return_json(2,'代理账号不能为空');
    	}
    	if(!array_key_exists('id',$data))
    	{
    		return  return_json(2,'代理信息不能为空');
    	}
    	$result = $this->where(['account'=>$data['account']])->find();
    	if (!$result && $result['status']!=1) {
    		return return_json(2,'代理账号不存在');
    	}
    	if($result['pid'] != $data['id']) {
    		return return_json(2,'此代理不是您下线代理！');
    	}
    	return return_json(1,'查询成功',$result);
    }
    /**
     * 代理商列表信息修改
     */
    public function newsPassword($data)
    {
        if(!array_key_exists('id',$data))
        {
            return  return_json(2,'代理账号不能为空');
        }
        $updata['password'] = md5('123456');
        $result1 = $this->where(['id'=>$data['id']])->update($updata);
        if (!$result1 ) {
            return return_json(2,'代理账号密码已重置');
        }else{
            $result = $this->where(['id'=>$data['id']])->find();
        }
        return return_json(1,'更新成功',$result);
    }
}