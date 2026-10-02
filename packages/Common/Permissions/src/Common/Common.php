<?php
/**
 * 公共方法
 */

namespace Common\Permissions\Common;
use Illuminate\Support\Str;
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




}
