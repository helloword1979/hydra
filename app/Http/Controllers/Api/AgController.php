<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Web\WebBaseController;
use App\Models\Api;
use App\Models\GameRecord;
use App\Models\Member;
use App\Models\MemberAPi;
use App\Models\Transfer;
use App\Services\AgService;
use Illuminate\Http\Request;
use \Exception;
use DB;

class AgController extends WebBaseController
{
    protected $service,$api;
    public function __construct() {
        $this->service = new AgService();
        $this->api = Api::where('api_name', 'AG')->first();
    }

    public function register($username,$password)
    {
        $res = $this->service->register($username, $password);
    }

    public function balance($username, $password) {
        //检查账号是否注册
        $member = $this->getMember();
        $member_api = $member->apis()->where('api_id', $this->api->id)->first();

        $return = [
            'Code' => 0,
            'Message' => 'success',
            'url' => '',
            'Data' => '',
        ];

        if (!$member_api)
        {
            $res = json_decode($this->service->register($username,$password), TRUE);
            if ($res['Code'] != 0)
            {
                $return['Code'] = $res['Code'];
                $return['Message'] = '开通账号失败！错误代码 '.$res['Code'].' 请联系客服';
                return $return;
            }

            //创建api账号
            $member_api = MemberAPi::create([
                'member_id' => $member->id,
                'api_id' => $this->api->id,
                'username' => $this->api->prefix.$member->name,
                'password' => $member->original_password
            ]);
        }


        $res = json_decode($this->service->balance($username, $password), TRUE);

        if ($res['Code'] == 0)
        {
            $member_api->update([
                'money' => $res['Data']
            ]);
            $return['Data'] = $res['Data'];
        } else {
            $return['Code'] = $res['Code'];
            $return['Message'] = '查询余额失败！错误代码 '.$res['Code'].' 请联系客服';
        }

        return $return;
    }

    public function deposit($username, $password, $amount, $amount_type = 'money')
    {
        //检查账号是否注册
        $member = $this->getMember();
        $member_api = $member->apis()->where('api_id', $this->api->id)->first();

        $return = [
            'Code' => 0,
            'Message' => 'success',
            'url' => '',
            'Data' => '',
        ];
        if (!$member_api)
        {
            $res = json_decode($this->service->register($username,$password), TRUE);
            if ($res['Code'] != 0)
            {
                $return['Code'] = $res['Code'];
                $return['Message'] = '开通账号失败！错误代码 '.$res['Code'].' 请联系客服';
                return $return;
            }

            //创建api账号
            $member_api = MemberAPi::create([
                'member_id' => $member->id,
                'api_id' => $this->api->id,
                'username' => $this->api->prefix.$member->name,
                'password' => $member->original_password
            ]);
        }
        //判断余额
        if ($amount_type == 'money')
        {
            if ($member->money < $amount)
            {
                $return['Code'] = -1;
                $return['Message'] = '账户余额不足';
                return $return;
            }
        } else {
            if ($member->fs_money < $amount)
            {
                $return['Code'] = -1;
                $return['Message'] = '账户余额不足';
                return $return;
            }
        }

        //先扣除用户余额
        

        $result = $this->service->deposit($username, $password,$amount);
        $res = json_decode($result, TRUE);

        if ($res['Code'] == "0")
        {
            try{
                
                DB::transaction(function() use($member_api, $res,$amount_type,$amount,$member,$result) {
                    $member->decrement($amount_type , $amount);
                    //平台账户
                    $member_api->increment('money', $amount);
                    //个人账户
                    //$member->decrement($amount_type , $amount);
                    //额度转换记录
                    Transfer::create([
                        'bill_no' => getBillNo(),
                        'api_type' => $member_api->api_id,
                        'member_id' => $member->id,
                        'transfer_type' => 0,
                        'money' => $amount,
                        'transfer_in_account' => $member_api->api->api_name.'账户',
                        'transfer_out_account' => $amount_type == 'money'?'中心账户':'返水账户',
                        'result' => $result
                    ]);
                    //修改api账号余额
                    $this->api->decrement('api_money' , $amount);
                });
            }catch(Exception $e){
                DB::rollback();
            }
        } else {
            $return['Code'] = $res['Code'];
            $return['Message'] = '错误代码 '.$res['Code'].' 请联系客服';
        }

        return $return;
    }

    /**
     * 提款
     * @param unknown $username
     * @param unknown $password
     * @param unknown $amount
     * @param string $amount_type
     * @return number[]|string[]|mixed[]
     */
    public function withdrawal($username, $password, $amount, $amount_type = 'money')
    {
        //检查账号是否注册
        $member = $this->getMember();
        $member_api = $member->apis()->where('api_id', $this->api->id)->first();

        $return = [
            'Code' => 0,
            'Message' => 'success',
            'url' => '',
            'Data' => '',
        ];

        if (!$member_api)
        {
            $res = json_decode($this->service->register($username,$password), TRUE);
            if ($res['Code'] != 0)
            {
                $return['Code'] = $res['Code'];
                $return['Message'] = '开通账号失败！错误代码 '.$res['Code'].' 请联系客服';
                return $return;
            }

            //创建api账号
            $member_api = MemberAPi::create([
                'member_id' => $member->id,
                'api_id' => $this->api->id,
                'username' => $this->api->prefix.$member->name,
                'password' => $member->original_password
            ]);
        }
        if ($member_api->money < $amount)
        {
            $return['Code'] = -1;
            $return['Message'] = '余额不足';
            return $return;
        }

        $result = $this->service->withdrawal($username, $password,$amount);
        $res = json_decode($result, TRUE);

        if ($res['Code'] == "0") {
            try{
                DB::transaction(function() use($member_api, $res,$amount_type,$amount,$member,$result) {
                    //平台账户
                    $member_api->decrement('money' , $amount);
                    //个人账户
                    $member->increment($amount_type , $amount);
                    //额度转换记录
                    Transfer::create([
                        'bill_no' => getBillNo(),
                        'api_type' => $member_api->api_id,
                        'member_id' => $member->id,
                        'transfer_type' => 1,
                        'money' => $amount,
                        'transfer_in_account' => $amount_type == 'money'?'中心账户':'返水账户',
                        'transfer_out_account' => $member_api->api->api_name.'账户',
                        'result' => $result
                    ]);
                    //修改api账号余额
                    $this->api->increment('api_money' , $amount);
                });
            }catch(\Exception $e){
                DB::rollback();
            }
        } else {
            $return['Code'] = $res['Code'];
            $return['Message'] = '错误代码 '.$res['Code'].' 请联系客服';
        }

        return $return;
    }

    public function login(Request $request)
    {
        $member = $this->getMember();
        $username = $member->name;
        $password = $member->original_password;
        $id = $request->get('id')?:0;
        //检查账号是否注册
        $member_api = $member->apis()->where('api_id', $this->api->id)->first();
        if (!$member_api)
        {
            $res = json_decode($this->service->register($username,$password), TRUE);
            if ($res['Code'] != 0)
            {
                echo '错误代码 '.$res['Code'].' 请联系客服';exit;
            }

            //创建api账号
            $member_api = MemberAPi::create([
                'member_id' => $member->id,
                'api_id' => $this->api->id,
                'username' => $this->api->prefix.$member->name,
                'password' => $member->original_password
            ]);
        }

        $res = json_decode($this->service->login($username, $password, $id), TRUE);

        if ($res['Code'] == 0)
        {
            $url = $res['Data'];

            return redirect()->to($url);
        } else {
            echo '错误代码 '.$res['Code'].' 请联系客服';exit;
        }

    }

    public function getGameRecord(){
//         set_time_limit(0);
        
//         $start_time = date('Y-m-d H:i:s', strtotime('-180 minutes'));
        $startDate = GameRecord::where('api_type', $this->api->id)->max('recalcuTime');
        if (!$startDate) {
            $startDate = date("Y-m-d H:i:s",strtotime("-365 day"));
        }
//         $startDate = date("Y-m-d",strtotime("-2 days"))." 00:00:00";
        $endDate = date("Y-m-d H:i:s");//每次同步60分钟的数据,strtotime($startDate)+ 60*60
        
        echo "$startDate,$endDate\n";
        $page = 1;
        $pagesize = 500;

        $res = $this->dy('', $startDate, $endDate,$page, $pagesize);
        
        if ($res['Code'] == 0) {
            
            $TotalPage   = $res["PageCount"];
            $TotalNumber = $res["TotalCount"];
//             echo "TotalPage:$TotalPage,TotalNumber:$TotalNumber\n";
            if ($TotalPage > 1) {
                if($TotalNumber>5000) { //这里还是有问题
                    
                    $page = $TotalPage;
                }else {
                    $pagesize = $TotalNumber;
                }
                
                $res = $this->dy('', $startDate, $endDate,$page, $pagesize);
            }else{
                
            }
            if ($res['Code'] == 0) {
                $data = $res["Data"]["Records"];
                $Page        = $res["PageIndex"];
                $PageLimit   = $res["PageSize"];
                $TotalNumber = $res["TotalCount"];
                $TotalPage   = $res["PageCount"];
//                 echo "TotalPage:$TotalPage,TotalNumber:$TotalNumber,PageLimit:$PageLimit,Page:$Page\n";
                
                if (count($data) > 0){
                    foreach($data as $value) {
                        if ($value["SceneID"]) {
                            $BillNo = $value["SceneID"];
                            $netAmount = $value["TransferAmount"];
                            $betAmount = $value["Cost"];
                        }else {
                            $BillNo = $value["BillNo"];
                            $netAmount = $value["NetAmount"];
                            $betAmount = $value["BetAmount"];
                        }
                        
                        if (!GameRecord::where('BillNo', $BillNo)->where('api_type', $this->api->id)->first()) {
                            $l = strlen($this->api->prefix);
                            $PlayerName = $value["PlayerName"];
                            $name = substr($PlayerName, $l);
                            $m = Member::where('name', $name)->first();
                            switch ($value['PlatformType']) {
                                case 'AGIN':
                                    $gameType = 1;
                                    break;
                                case 'HUNTER':
                                    $gameType = 2;
                                    break;
                                case 'AGTEX':
                                    $gameType = 6;
                                    break;
                                case 'XIN':
                                    $gameType = 3;
                                    break;
                                default :
                                    $gameType = 7;
                            }
                            GameRecord::create([
                                'billNo' => $BillNo,
                                'playerName' => $PlayerName,
                                'agentCode' => $value["AgentCode"],
                                'gameCode' => $value["GameCode"],
                                'netAmount' => $netAmount,
    //                                 'betTime' => date('Y-m-d H:i:s', strtotime($value["Bet"]) + 12*60*60),    // 如果与游戏返回的时间相差12小时则补齐
                                'betTime' => date('Y-m-d H:i:s', strtotime($value["CreateDate"])),
                                'gameType' => $gameType,
                                'betAmount' => $betAmount,
                                'validBetAmount' => $value["ValidBetAmount"],
                                'flag' => $value["Flag"],
                                'playType' => $value["PlayType"],
                                'currency' => $value["Currency"],
                                'tableCode' => $value["TableCode"],
                                'loginIP' => $value["LoginIP"],
                                'recalcuTime' => $value["RecalcuTime"],
                                'platformID' => $value["PlatformID"],
                                'platformType' => $value["PlatformType"],
                                'stringEx' => $value["StringEx"],
                                'remark' => $value["Remark"],
                                'round' => $value["Round"],
                                'api_type' => $this->api->id,
                                'name' => $name,
                                'member_id' => $m?$m->id:0
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function dy($username, $start_time, $end_date,$page, $pagesize)
    {
        return json_decode($this->service->betrecord($username, $start_time, $end_date,$page, $pagesize), TRUE);
    }
}
