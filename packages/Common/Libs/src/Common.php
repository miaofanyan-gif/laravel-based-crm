<?php
/**
 * 公共方法
 *
 * Date: 2021/6/2
 * Time: 15:33
 */


namespace Common\Libs;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class Common {

    /**
     * 递归遍历层级
     */
    public function getTree($data, $pid = 0)
    {
        $tree = [];
        foreach($data as $v){
            //匹配子记录
            if ($v['pid'] == $pid) {
                if($v['type'] == 1)
                    $v['children'] = $this->getTree($data, $v['id']);
                //如果子元素为空则unset()进行删除，说明已经到该分支的最后一个元素了
                if(empty($v['children'])){
                    unset($v['children']);
                }
                $tree[] = $v;
            }
        }
        return $tree;
    }

    /**
     * 递归遍历层级
     */
    public function getFormKeyTree($data, $key='pid', $pid = 0)
    {
        $tree = [];
        foreach($data as $v){
            if ($v[$key] == $pid) {
                $v['children'] = $this->getFormKeyTree($data, $key, $v['id']);
                $tree[] = $v;
            }
        }
        return $tree;
    }


    /**
     * 根据指定字段对二维数组进行分组
     *
     * @param array $array 要分组的二维数组
     * @param string $keyField 用于分组的字段名
     * @return array 分组后的数组
     */
    public function groupByField($array,  $keyField) {
        $groupedArray = [];
        foreach ($array as $item) {
            if (isset($item[$keyField])) {
                $key = $item[$keyField];
                $groupedArray[$key][] = $item;
            }
        }
        return $groupedArray;
    }


    /**
     * 下载excel文档
     */
    static public function documentDownload($filename, $data = [])
    {
        $csvFileName = $filename ?? '文档';
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $csvFileName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        $fp = fopen('php://output', 'a'); //打开output流
        foreach ($data as $v) {
            $rowData = $v;
            mb_convert_variables('GBK', 'UTF-8', $rowData);
            fputcsv($fp, $rowData); //将数据格式化为CSV格式并写入到output流中
        }
        fclose($fp);
        exit();
    }


    /**
     * 导出报表
     * @param $result
     * @param $header
     * @param $date
     * @param string $filename
     */
    static public function handleExportCsv($result, $header, $date, $filename = '报表')
    {
        $data = [];
        //处理需要的数据放进新数组中
        foreach ($result as $key=>$value){
            foreach ($header as $kk=>$vv){
                $data[$key][$kk] = is_numeric($value[$kk]) && strpos($value[$kk],'.')  === false && strlen($value[$kk]) > 11 ? " \t".$value[$kk] : ($value[$kk]?$value[$kk]:'');
//                $data[$key][$kk] = $value[$kk];
            }
        }
        array_unshift($data,$header);//嵌入头部信息
        if(isset($date[0])){
            array_unshift($data, ['数据日期：'.$date[0] . '至' . $date[1]]);//写入第一行信息
        }
        self::documentDownload($filename.date('Ymd').'.csv',$data);
    }

    /**
     * 拼接header 信息
     * @param $header
     * @return array
     */
    static public function handleHeader($header){
        $result = [];
        foreach ($header as $key=>$item){
            $result[] = ['key'=>$key,'value' => $item];
        }
        return $result;
    }

    /**
     * 随机小数
     */
    static function randomFloat($min = 0, $max = 1) {
        $num = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return sprintf("%.2f",$num);  //控制小数后几位
    }
    /**
     * 把日期处理成年月日
     */
    static function dateToYearMonthDate($date = '1970-01-01', $str = 'all') {
        switch ($str){
            case 'all':
                $result = [Str::substr($date,0,4),Str::substr($date,0,7),Str::substr($date,0,10)];
                break;
            case 'year':
                $result = Str::substr($date,0,4);
                break;
            case 'month':
                $result = Str::substr($date,0,7);
                break;
            case 'day':
                $result = Str::substr($date,0,10);
                break;
            default:
                $result = $date;
        }
        return $result;
    }

    /**
     * 获取当前用户登录的token
     * @return array|string|null
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    static public  function getLoginToken(){
        $header = request()->header('Authorization', request()->get('token',''));
        if (Str::startsWith($header, 'Bearer ')) {
            return Str::substr($header, 7);
        }
        return $header;
    }

    /**
     * 随机数据
     * data 需要随机的数组
     * 需要排除的id
     */
    static public function random($data, $id)
    {
        $dataUser = $data[array_rand($data)];
        if ($dataUser == $id) {
            $dataUser = self::random($data, $id);
        }
        return $dataUser;
    }


    /**
     * 无限循环
     * $action  闭包方法，
     * 注意⚠️ $action一定要返回false；否则会死循环。
     */
    static public function foreachUnlimited($action,$i=1)
    {
        $result = true;
        for (; $result && $i <= 1000; $i++) {
            $result = $action($i);
        }
    }


    /**
     * 金额装换
     * 分 -> 元
     * @param $value
     * @return float|int
     */
    static public  function centsToYuan($value) {
        return isset($value) ? (float)((int)$value/100) : 0;
    }


    /**
     * 金额装换并保留小数
     * 分 -> 元
     * @param $value
     * @param $bit
     * @return float|int
     */
    static public  function centsToYuanBit($value,$bit) {
        return number_format($value/100,2);
    }


    /**
     * 保留小数
     * @param $value
     * @param $decimal
     * @return float|int
     */
    static public  function numberToDecimal($value,$decimal=2) {
        return number_format($value,$decimal);
    }

    /**
     * 元转亿
     * @param $value
     * @param $decimal
     * @return float|int
     */
    static public  function decimalToHundredMillion($value,$decimal=2) {
        $num = $value/(100*100*10000);
        return number_format($num,$decimal)+0;
    }

    /**
     * 元转万
     * @param $value
     * @param $decimal
     * @return float|int
     */
    static public  function decimalToTenThousand($value,$decimal=2) {
        return number_format(bcdiv($value,10000,4),$decimal);
    }

    /**
     * 计算是否是闰年
     * @param $year
     * @return bool
     */
    static public function isLeapYear($year=null){
        if($year)
            $year = date('Y');
        if (($year % 4 == 0 && $year % 100 != 0) || $year % 400 == 0) {
            return 366; // 是闰年
        } else {
            return 365; // 不是闰年
        }
    }
    /**
     * 获取当前季度
     * @return bool
     */
    static public function getCurrentQuarter(){
        $current_month = date('n'); // 获取当前月份，n表示不带前导零
        $year = date('Y');
        switch ($current_month) {
            case 1:
            case 2:
            case 3:
                $quarter = 1; //第一季度
                break;
            case 4:
            case 5:
            case 6:
                $quarter = 2; //第二季度
                break;
            case 7:
            case 8:
            case 9:
                $quarter = 3; //第三季度
                break;
            case 10:
            case 11:
            case 12:
                $quarter = 4; //第四季度
                break;
        }
        $data['quarter'] = $quarter;
        //所使用的日期均为当前年的开始日期
        $data['month'] = [
            1 => [$year . '-01', $year . '-03'],
            2 => [$year . '-01', $year . '-06'],
            3 => [$year . '-01', $year . '-09'],
            4 => [$year . '-01', $year . '-12'],
        ];
        return $data;
    }

    /**
     * 计算两个日期的天数
     * @param $start
     * @param $end
     * @return float|int
     */
    static public  function dateToDayNum($start,$end) {
        return (strtotime($end) - strtotime($start)) / 86400 + 1; // 计算天数
    }

    /**
     * 计算两个月之间的天数
     * @param $start_month
     * @param $end_month
     * @return float|int
     * @throws \Exception
     */
    static public  function monthToDayNum($start_month,$end_month) {
        $start_date = $start_month.'-01';
        $end_date = (new \DateTime($end_month.'-01'))->format('Y-m-t');
        return self::dateToDayNum($start_date,$end_date); // 计算天数
    }

    /**
     * 计算两个月之间的月数
     * @param $start
     * @param $end
     * @return int
     */
    static public  function dateToMonthNum($start,$end) {
        $startYear = Str::before($start,'-'); // 获取开始年
        $startMonth = Str::substr($start,5,2); // 获取开始月
        $endYear = Str::before($end,'-'); // 获取结束年
        $endMonth = Str::substr($end,5,2); // 获取结束月
        return intval(($endYear - $startYear) * 12) + intval($endMonth - $startMonth) + 1; // 计算月份数
    }
    /**
     * 计算周期的开始日期（上周六-本周五为一个周期）
     * 周一至周五 → 取上周五
     * 周六 → 取上周五
     * 周日 → 取上周五
     * @return string
     */
    static public function getPeriodSartDate($gap=3)
    {
        $today = new \DateTime();
        $week = (int)$today->format('N');
        $days = ($week + $gap) % 7;
        if ($days == 0) {
            $days = 7; // 周日情况
        }
        return $today->modify("-{$days} days")->format('Y-m-d');
    }

    /**
     * 两个数据转化为计算百分比
     * @param $current_data float 当前
     * @param $prev_data
     * @return float|int
     */
    static public  function dataToPerCent($current_data,$prev_data) {
        $result = 0;
        if ($current_data > $prev_data && $prev_data > 0) {
            $result = self::numberToDecimal(($current_data - $prev_data) / $prev_data * 100) . '%';
        }
        if ($prev_data > $current_data && $prev_data > 0) {
            $result = self::numberToDecimal(($prev_data - $current_data) / $prev_data * 100) . '%';
        }
        return $result;
    }

    /**
     * 计算数据的占比
     * @param $cost float 消耗
     * @param $total float 总消耗
     * @return string|int
     */
    static public  function costToPerCent($cost,$total) {
        $result = 0;
        if ($total > 0) {
            $result = self::numberToDecimal($cost / $total * 100) . '%';
        }
        return $result;
    }

    /**
     * 浏览器友好的变量输出 采用thinkphp  zhao
     * @param mixed $var 变量
     * @param boolean $echo 是否输出 默认为True 如果为false 则返回输出字符串
     * @param string $label 标签 默认为空
     * @param boolean $strict 是否严谨 默认为true
     * @return void|string
     */
    static public function dump($var, $echo=true, $label=null, $strict=true) {
        $label = ($label === null) ? '' : rtrim($label) . ' ';
        if (!$strict) {
            if (ini_get('html_errors')) {
                $output = print_r($var, true);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            } else {
                $output = $label . print_r($var, true);
            }
        } else {
            ob_start();
            var_dump($var);
            $output = ob_get_clean();
            if (!extension_loaded('xdebug')) {
                $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            }
        }
        if ($echo) {
            echo($output);
            return null;
        }else
            return $output;
    }

    /**
     * 浏览器友好的变量输出   zhao
     * @param mixed $var 变量
     */
    static public function printR($var){
        echo '<pre>';
        print_r($var);
        echo '</pre>';
    }

    /**
     * 获取毫秒时间
     */
    static public function getMicroTime(){
        list($t1, $t2) = explode(' ', microtime());
        return (string)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }
    /**
     * 获取当前操作requestId毫秒时间
     * @param string $type
     * @return string
     */
    static public function getRequestId($type = 'campaign')
    {
        $microtime = self::getMicroTime();
        return (string)'i_' . $type . '_' . $microtime . '_' . uniqid() . '_' . uniqid() . '_' . mt_rand(1000000000, 9999999999);
    }
    /**
     * 获取当前操作URL
     * @param string $request_url
     * @return string
     */
    static public function getCurrentUrl($request_url = ''){
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        if($request_url){
            //return  "$protocol$_SERVER[HTTP_HOST]/oauth/weibo/getAuthorize";
            return  "$protocol$_SERVER[HTTP_HOST]$request_url";
        }else{
            return  "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        }
    }
    /**
     * 分页数据处理
     * @param $page int 当前页数
     * @param $per_page int 每页条数
     * @param $total int 数据总条数
     * @return array
     */
    static public function pageHandle($page, $per_page, $total)
    {
        return [
            'current_page' => $page,
            'from' => ($page - 1) * $per_page,
            'to' => $page * $per_page,
            'last_page' => round($total / $per_page),
            'per_page' => $per_page,
            'total' => $total
        ];
    }

    /**
     * 根据日期判断是否在有效期内
     * @param $start_date
     * @param $end_date
     * @param string $prefix 字符串拼接
     * @return string|null
     */
    static public function dateIfValid($start_date,$end_date,$prefix='托管任务'){
        $date = date('Y-m-d');
        if($end_date < $date)
            $string = $prefix.'已结束';
        else if($start_date <= $date && $end_date >= $date)
            $string = null;
        else
            $string = $prefix.'未开始';
        return $string;
    }

    /**
     * excel表格日期处理
     * @param $date
     * @return array
     */
    static public  function excelDateHandle ($date){
        if (is_numeric($date)) {
            $date = gmdate('Y-m-d', ($date - 25569) * 24 * 60 * 60);
        }
        $date = new \DateTime($date);
        return [
            'year'  => $date->format('Y',),
            'month' => $date->format('n月'),
            'date'  => $date->format('Y-m-d'),
        ];
    }

    /**
     * 两个数字或字符串之间精度求和
     * @param array $nums 加数的数组
     * @param string $scale 设置结果中的小数位数
     * @return int|string
     */
    static public function mathAdd(array $nums, $scale = '2')
    {
        $total = 0;
        foreach ($nums as $num) {
            $total = bcadd($total, $num, $scale);
        }
        return $total;
    }

    /**
     * 两个数字或字符串之间精度求差
     * @param $minuend int/string 被减数
     * @param $subtrahend int/string 减数
     * @param string $scale 设置结果中的小数位数
     * @return string
     */
    static public function mathSub($minuend, $subtrahend, $scale = '2')
    {
        return bcsub($minuend, $subtrahend, $scale);
    }


    /**
     * 剩余额度
     * @param float $used_credit_amount  授信额度
     * @param float $init_used_credit_amount  初始化授信额度
     * @param float $rechargeAmount  充值金额
     * @param float $receiveAmount 回款金额
     * @param float $refundAmount 汇款的退款金额
     * @param int/float $todayAmounts 当天充值的金额
     * @return string
     */
    public static function quantityAmount($used_credit_amount,$init_used_credit_amount, $oldRechargeAmount,$rechargeAmount, $receiveAmount, $refundAmount=0, $todayAmounts = 0)
    {
        // 计算公式
        // 项目可用额度金额=项目信用额度-项目初始已使用额度-(启用日期后)累计充值金额+(启用日期后)累计项目收款金额-(启用日期后)累计项目收款退款金额
        $amounts = Common::mathSub($used_credit_amount, $init_used_credit_amount);
        $amounts = Common::mathAdd([$amounts, $receiveAmount]);
        $amounts = Common::mathSub($amounts, $oldRechargeAmount);
        $amounts = Common::mathSub($amounts, $rechargeAmount);
        $amounts = Common::mathSub($amounts, $refundAmount);
        $amounts = Common::mathSub($amounts, $todayAmounts);
        return floatval($amounts);
    }

    /**
     * 使用额度
     * @param float $rechargeAmount  充值金额
     * @param float $receiveAmount 回款金额
     * @param float $refundAmount 汇款的退款金额
     * @param int/float $todayAmounts 当天充值的金额
     * @return string
     */
    public static function useAmount($rechargeAmount, $receiveAmount, $refundAmount, $todayAmounts = 0)
    {
        // 计算公式
        // 项目已经使用额度=(启用日期后)累计充值金额-(启用日期后)累计项目收款金额+(启用日期后)累计项目收款退款金额
        $amounts = Common::mathAdd([$rechargeAmount, $todayAmounts,$refundAmount]);
        $amounts = Common::mathSub($amounts, $receiveAmount);
        return floatval($amounts);
    }

    static public  function getMonthlyTenDayBreakdown($start_date, $end_date,$cut_day=10) {
        $start = new \DateTime($start_date);
        $end = new \DateTime($end_date);

        if ($start > $end) {
            $temp = $start;
            $start = $end;
            $end = $temp;
        }

        $result = [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'monthly_breakdown' => []
        ];

        // 首先获取所有涉及的月份
        $currentMonth = clone $start;
        $currentMonth->modify('first day of this month');

        while ($currentMonth <= $end) {
            $monthYear = $currentMonth->format('Y-m');

            // 计算该月的实际日期范围
            $monthStart = clone $currentMonth;
            $monthEnd = clone $currentMonth;
            $monthEnd->modify('last day of this month');

            if ($monthYear === $start->format('Y-m')) {
                $monthStart = clone $start;
            }
            if ($monthYear === $end->format('Y-m')) {
                $monthEnd = clone $end;
            }

            // 为该月生成10天周期
            $monthPeriods = self::generateMonthlyTenDayPeriods($monthStart, $monthEnd,$cut_day);

            $result['monthly_breakdown'][] = [
                'month' => $monthYear,
                'month_start' => $monthStart->format('Y-m-d'),
                'month_end' => $monthEnd->format('Y-m-d'),
                'total_days' => $monthStart->diff($monthEnd)->days + 1,
                'ten_day_periods' => $monthPeriods,
            ];

            $currentMonth->modify('+1 month');
        }

        $result['total_months'] = count($result['monthly_breakdown']);

        return $result;
    }

    static public function generateMonthlyTenDayPeriods($monthStart, $monthEnd,$cut_day=10) {
        $periods = [];
        $current = clone $monthStart;

        while ($current <= $monthEnd) {
            $periodStart = clone $current;
            $periodEnd = clone $current;
            $periodEnd->modify('+'.($cut_day-1).' days');

            if ($periodEnd > $monthEnd) {
                $periodEnd = clone $monthEnd;
            }
            $periods[$periodStart->format('Y-m-d')] = $periodEnd->format('Y-m-d');
            $current = clone $periodEnd;
            $current->modify('+1 day');
        }

        return $periods;
    }

    /**
     * 字符串数组转整数类型数组
     * @param $ids
     * @return array|int[]
     * @throws \Exception
     */
    static public  function strArrToIntArr($ids) {
        if (!is_array($ids) && !is_numeric($ids)) {
            throw new \Exception('参数错误，请联系系统管理员', 500);
        }
        return array_map(function ($value){
            return intval($value);
        },is_numeric($ids)?[$ids]:$ids);
    }


    /**
     * 生成充值单号
     * @return string
     */
    public static function generateFinanceNumber(): string
    {
        $date = date('Ymd');
        $sequence = 1;
        $sequencePart = str_pad($sequence, 3, '0', STR_PAD_LEFT);
        $randomPart = str_pad(mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
        return $date . $sequencePart . $randomPart;
    }



}
