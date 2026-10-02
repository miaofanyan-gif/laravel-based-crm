<?php
/**
 * 文件压缩ZIP下载
 *
 * User: 李斌
 * Date: 2018/8/21
 * Time: 14:04
 */
namespace Common\Libs;


use \ZipArchive;
class CreateZip{
    public $filename;
    public $filePath;
    public $realname;
    public function __construct()
    {
        set_time_limit(0);
    }

    /**
     * 压缩生成ZIP
     *
     * @Params String $filename 文件名称
     * @Params Array $filePath 文件路径(一维数组)
     */
    public function create($filename, $filePath, $realname = null)
    {
        $filename = $filename.".zip";//请注意判断该目录下有可写的权限
        $this->filename = $filename;
        $this->filePath = $filePath;
        $this->realname = $realname;
        $this->Zip();
        if(strpos(PHP_OS, 'WIN') === false){
            $rel_filename = $filename;
        }else{
            $rel_filename = mb_convert_encoding($filename, 'GBK', 'UTF-8');
        }
        /*
        if(!file_exists($rel_filename))
        {
            die('文件创建失败');//即使创建，仍有可能失败。。。。
        }
        */

        $filesize = filesize($rel_filename);//文件大小

        set_time_limit(0);
        ini_set('memory_limit', '512M');
        //返回的文件(流形式)
        header("Content-Type: application/zip");
        //按照字节大小返回
        header("Accept-Ranges: bytes");
        //返回文件大小
        header("Accept-Length: ".$filesize);
        //这里客户端的弹出对话框，对应的文件名
        header("Content-Disposition: attachment; filename=".preg_replace('/^.+[\\\\\\/]/', '', $rel_filename));

        ob_end_clean();
        @readfile($rel_filename);

        @unlink($rel_filename); //删除文件
    }
    /**
     * 压缩生成ZIP
     *
     * @Params String $filename 文件名称
     * @Params Array $filePath 文件路径(一维数组)
     */
    public function createBak($filename, $filePath, $realname = null)
    {
        $filename = $filename.".zip";//请注意判断该目录下有可写的权限
        $this->filename = $filename;
        $this->filePath = $filePath;
        $this->realname = $realname;
        $this->Zip();
        if(strpos(PHP_OS, 'WIN') === false){
            $rel_filename = $filename;
        }else{
            $rel_filename = mb_convert_encoding($filename, 'GBK', 'UTF-8');
        }
        /*
        if(!file_exists($rel_filename))
        {
            die('文件创建失败');//即使创建，仍有可能失败。。。。
        }
        */
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header('Content-disposition: attachment; filename='.preg_replace('/^.+[\\\\\\/]/', '', $rel_filename)); //文件名
        header("Content-Type: application/zip"); //zip格式的
        header("Content-Transfer-Encoding: binary"); //告诉浏览器，这是二进制文件
        header('Content-Length: '. filesize($rel_filename)); //告诉浏览器，文件大小
        ob_end_clean();
        @readfile($rel_filename);
        @unlink($rel_filename); //删除文件
    }

    /**
     * 压缩生成Zip文件
     */
    public function Zip()
    {
        $filename = $this->filename;
        $filePath = $this->filePath;
        $realname = $this->realname;
        if(!file_exists($filename))
        {
            //重新生成文件
            $zip = new ZipArchive();//使用本类，linux需开启zlib，windows需取消php_zip.dll前的注释
            if ($zip->open($filename, ZIPARCHIVE::CREATE)!==TRUE)
            {
                exit('无法打开文件，或者文件创建失败');
            }

            foreach( $filePath as $key=>$val)
            {
                if(file_exists($val))
                {
                    if($realname){
                        $childname = $realname[$key];
                    }else{
                        $childname = $val;
                    }

                    //第二个参数是放在压缩包中的文件名称，如果文件可能会有重复，就需要注意一下
                    $res = $zip->addFile( $val, preg_replace('/^.+[\\\\\\/]/', '', $childname));
                    var_dump($res);
                }
            }
            $zip->close();//关闭
        }
    }

}

