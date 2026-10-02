<?php
/**
 * csv文件导出
 */

namespace Common\Libs;


use Illuminate\Support\Facades\Config;


class FileCsvExport {

    /**
     * 文件后缀
     */
    public $suffix = '.csv';
    /**
     * $file_name
     * @var string
     */
    public $file_name = '表格';

    /**
     *
     * @var array
     */
    public $header = [];

    /**
     * 是否异步导出
     * @var bool
     */
    public $is_sync = false;

    //打开的文件流
    public $fp;

    public function __construct($header,$date,$file_name) {
        $this->header = $header;
        $this->date = $date;
        $this->file_name = $file_name;
    }

    /**
     * 写入表格头部
     * @return void
     */
    public function writeCsvHeader()
    {
        if ($this->is_sync == true) {
            $filePath = env('CSV_PATH') . $this->file_name . $this->suffix;//文件存放地址
            touch($filePath);//创建文件
        } else {
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="' . $this->file_name . $this->suffix . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
        }
        $this->fp = fopen('php://output', 'a'); //打开output流

        if($this->date){
            $date = ['数据日期：'.$this->date[0] . '至' . $this->date[1]];
            mb_convert_variables('GBK', 'UTF-8', $date);
            fputcsv($this->fp, $date); //将表格头部写入
        }
        mb_convert_variables('GBK', 'UTF-8', $this->header);
        fputcsv($this->fp, $this->header); //将表格头部写入

    }

    /**
     * 写入导出表格的数据
     * @param $data
     * @return void
     */
    public function writeCsvBody($data)
    {
        foreach ($data as $rowData) {
            $value = [];//数据重新清洗
            foreach ($this->header as $kk=>$vv){
                $value[$kk] = is_numeric($rowData[$kk]) && strpos($rowData[$kk],'.')  === false && strlen($rowData[$kk]) > 11 ? " \t".$rowData[$kk] : ($rowData[$kk]?$rowData[$kk]:'');
            }
            mb_convert_variables('GBK', 'UTF-8', $value);
            fputcsv($this->fp, $value); //将数据格式化为CSV格式并写入到output流中
        }
    }

    /**
     * 文件写入完成
     * @return void
     */
    public function writeCsvFooter(){
        fclose($this->fp);
        exit();
    }

}

