<?php

namespace JoyGc\Dashboard\Import;

use Common\Exceptions\RepositoryException;
use JoyGc\Dashboard\Server\AccountRole;
use Illuminate\Support\Facades\Log;

class CommonImport
{
    /**
     * 定义消耗导入的表格头部
     * @var string[]
     */
    protected $cost_header = [
        //        'number'=>'编号','date'=>'日期','advertiser_id'=>'广告主id','cost'=>'消耗'
        'date' => '日期', 'advertiser_id' => '广告主id', 'cost' => '消耗', 'optimize' => '运营', 'group' => '组长'
    ];
    /**
     * 定义字段对应的说明
     * @var string[]
     */
    protected $account_header = [
        'media_type'     => '媒体', 'media_second_type' => '媒体二级', 'advertiser_name' => '账户名称', 'advertiser_id' => '账户id', 'industry' => '行业',
        'company_name'   => '客户公司名称', 'brand_name' => '产品名称/品牌',
        'area'           => '区域', 'sale' => '销售', 'manage' => '运营负责人', 'group' => '运营组长', 'optimize' => '运营人员',
        'operation_mode' => '运营方式',
        //'port'=>'端口','brand_from'=>'品牌归属','user_level'=>'客户级别','zi_account_id'=>'子账户ID','erjihangye'=>'二级行业'
    ];

    /**
     * 定义字段对应的说明
     * @var string[]
     */
    protected $check_header = [
        'media_type'             => '媒体', 'media_second_type' => '媒体二级', 'advertiser_name' => '账户名称', 'advertiser_id' => '账户id', 'industry' => '行业',
        'company_name'           => '客户公司名称', 'brand_name' => '产品名称/品牌',
        'area'                   => '区域', 'sale' => '销售', 'manage' => '运营负责人', 'group' => '运营组长', 'optimize' => '运营人员',
        'operation_mode'         => '运营方式',
        'media_id'               => '媒体', 'media_second_id' => '媒体二级', 'industry_id' => '行业', 'area_id' => '区域', 'sale_id' => '销售',
        'manage_id'              => '运营负责人', 'group_id' => '运营组长', 'optimize_id' => '运营人员', 'operation_mode_id' => '运营方式',
        'optimize_department_id' => '运营部门', 'sale_manage_name' => '销售组长', 'sale_director_name' => '销售负责人', 'sale_department_name' => '销售部门'
    ];


    //媒介验证字段
    protected $media_user_check = [
        'account' => [
            'media_type', 'media_second_type', 'advertiser_name', 'advertiser_id', 'industry',
            'company_name', 'sale'
        ],
        'cost'    => [
            //            'media_id','media_second_id','advertiser_name','advertiser_id','industry_id',
            //            'company_name','area_id','sale_id'
            //'advertiser_name','advertiser_id','industry_id','company_name','area_id','sale_id'
        ]
    ];
    //运营验证字段
    protected $optimize_user_check = [
        'account' => [
            'advertiser_id', 'advertiser_name', 'manage', 'group', 'optimize', 'operation_mode',
        ],
        'cost'    => [
            'advertiser_id', 'advertiser_name', 'manage_id', 'group_id', 'optimize_id', 'operation_mode_id', 'optimize_department_id'
            //            'brand_name',
        ],
    ];

    public $result = [];//导入的数据结果
    public $checkData = [];//需要检查的数据
    public $user_role = null;//用户角色
    public $accountRepository;//账户
    public $userCacheList;//用户信息
    public $userAreaCacheList;//用户区域信息
    public $userOptimizeCacheList;//用户运营信息

    public $is_media = false; //是否是运营角色
    public $is_admin = false; //是否是管理员

    /**
     * 自定执行
     * CommonImport constructor.
     */
    public function __construct()
    {
        $userRepos = app('\JoyGc\Dashboard\Repositories\UserRepositoryEloquent');
        $this->accountRepository = app('\JoyGc\Dashboard\Repositories\AccountRepository');
        $this->userCacheList = $userRepos->userCacheList();
        Log::channel('user_info')->info('用户信息', [$this->userCacheList]);
        $this->userAreaCacheList = $userRepos->userAreaCacheList();
        // 运营数据
        $this->userOptimizeCacheList = $userRepos->userOptimizeCacheList(null,1);

        //检查字段权限
        [$roleIndex, $searchData] = AccountRole::getAccountRole();
        Log::channel('user_info')->info('用户标识', [$roleIndex]);
        switch ($roleIndex['roleGroup']) {
            case 'admin':
                $this->is_admin = true;
                $this->checkData = $this->media_user_check;
                break;
            case 'media':
                $this->is_media = true;
                $this->checkData = $this->media_user_check;
                break;
            default:
                $this->checkData = $this->media_user_check;
        }
    }


    /**
     * 检查入库的数据
     * @param $data
     * @param $type
     * @return bool
     * @throws RepositoryException
     */
    protected function checkImportData($data, $type = 'cost', $key = 0)
    {
        if (!isset($this->checkData[$type])) {
            return true;
        }

        $checkData = $this->checkData[$type];
        if ($type == 'cost' && !empty($data['sale_id'])) {
            $checkData = array_merge($checkData,
                ['sale_manage_name', 'sale_director_name', 'sale_department_name']);
        }

        foreach ($checkData as $field) {
            if ($type == 'cost') {
                $str = ',请检查当前员工是否离职，如果离职，请更换账户信息的销售后再上传消耗';
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new RepositoryException('日期' . $data['date'] . '，广告主' . $data['advertiser_id'] . '数据缺少' . $this->check_header[$field].($field=='sale_manage_name'?$str:null), 10005);
                }
            }
            if ($type == 'account') {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new RepositoryException('您导入的信息中第' . ($key + 1) . '行，没有' . $this->check_header[$field] . '，检查后重新导入', 5001);
                }
            }
        }
        return true;
    }


    /**
     * 日期处理
     * @param $date
     * @return array
     */
    protected function dateHandle($date)
    {
        if (is_numeric($date)) {
            $date = gmdate('Y-m-d', ($date - 25569) * 24 * 60 * 60);
        }
        $time = strtotime($date);
        $result = [
            'year'  => date('Y', $time),
            'month' => date('Y-m', $time),
            'date'  => date('Y-m-d', $time),
        ];
        $result['quarter'] = ceil(date('m', $time) / 3);
        $result['week'] = date('W', $time);
        return $result;
    }

}
