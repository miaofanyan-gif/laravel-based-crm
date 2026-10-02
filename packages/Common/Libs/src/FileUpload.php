<?php
/**
 * 文件上传与处理
 *
 * User: 李斌
 * Date: 2019/5/27
 * Time: 10:13
 */

namespace Common\Libs;


use Illuminate\Support\Facades\Config;
use Intervention\Image\ImageManagerStatic as Image;


class FileUpload {

    // 文件域名
    public $domain_file;

    // 缩略图定义
    private $thumb_width = 200;
    private $thumb_height = 200;


    // 文件上传路径
    private $save_path;
    public $save_prefix = 'assets/';
    private $save_dir = 'mymaterial/';


    // 图片格式
    private $ext_image = array('JPG', 'PNG', 'JPEG', 'GIF');
    // 视频格式
    private $ext_video = array('MP4', 'MOV', 'AVI', 'MPG', 'WMV');

    public function __construct() {
        $this->domain_file = Config::get('filesystems.domain_file') . '/';
        $this->save_dir = $this->save_dir . date('Ymd').'/';
        $this->save_path = $this->save_prefix . $this->save_dir;
        if(!is_dir($this->save_path)) {
            mkdir($this->save_path, 0777,true);
        }
    }

    /**
     * 文件上传
     * (单个上传)(普通上传)
    */

    public function upload($file, $thumb_width = 200, $thumb_height = 200) {
        $this->thumb_width = $thumb_width;
        $this->thumb_height = $thumb_height;
        // 判断文件有效性
        if (!$file->isValid()) {
            return array(
                'code' => 400,
                'msg' => '无效的文件',
                'data' => null,
            );
        }
        // 文件大小
        $filesize = $file->getClientSize();
        // 文件扩展名
        $ext = $file->getClientOriginalExtension();
        if(!in_array(strtoupper($ext), array_merge($this->ext_image, $this->ext_video))) {
            return array(
                'code' => 400,
                'msg' => '文件上传格式错误。图片格式：'.implode(',',$this->ext_image).',视频格式：'.implode(',',$this->ext_video),
                'data' => null,
            );
        }

        // 文件的路径及名称定义
        $file_name = date('Ymd').mt_rand(1, 100000);
        $file_ext = $file_name.'.'.$ext;
        $url = $this->save_dir . $file_ext;
        $move_url = $this->save_path . $file_ext;
        $path = $file->move($this->save_path, $move_url);

        // 图片文件
        if(in_array(strtoupper($ext), $this->ext_image)) {
            $url_thumb = $this->image_to_thumb($move_url, $file_ext);
            return array(
                'code' => 200,
                'msg' => 'success',
                'data' =>  array(
                    'cover_img' => $this->domain_file . $url,
                    'cover_thumb' => $this->domain_file . $url_thumb,
                    'url' => $this->domain_file . $url,
                    'material_type' => strtoupper($ext),
                    'file_size' => $filesize,
                ),
            );
        } else { // 视频文件
            $command = 'ffmpeg -i '.$move_url.' 2>&1';
            ob_start();
            passthru($command);
            $info = ob_get_contents();
            ob_end_clean();
            $data = array();
            if ((preg_match("/Video:(.*)\), (.*)\), (\d+x\d+)(\s|,)(.*)Audio/isU", $info, $match)) || (preg_match("/Video:(.*), (.*), (\d+x\d+)(\s|,)(.*)Metadata/isU", $info, $match))) {
                $data['vcodec'] = strtoupper(trim(explode('(', $match[1])[0])); //视频编码格式
                //$data['vformat'] = $match[2]; //视频格式
                $data['resolution'] = $match[3]; //视频分辨率
                $arr_resolution = explode('x', $match[3]);
                $data['width'] = $arr_resolution[0];
                $data['height'] = $arr_resolution[1];
            }

            //第一帧图片作为封面图
            $cover_path = $this->save_dir."cover_".$file_name.".jpg";
            $move_cover_path = $this->save_path."cover_".$file_name.".jpg";
            $command2 = 'ffmpeg -i '.$move_url.' -y -f image2 -ss 00:00:01 -t 0.001 -s '.$data['width']."x".$data['height']." ".$move_cover_path;
            ob_start();
            passthru($command2);
            ob_end_clean();

            // 缩略图生成
            $url_thumb = $this->image_to_thumb($move_cover_path, $file_name.".jpg");
            return array(
                'code' => 200,
                'msg' => 'success',
                'data' =>  array(
                    'cover_img' => $this->domain_file.$cover_path,
                    'cover_thumb' => $this->domain_file . $url_thumb,
                    'url' => $this->domain_file . $url,
                    'material_type' => strtoupper($ext),
                    'file_size' => $filesize,
                ),
            );
        }
    }

    /**
     * 缩略图生成
     *
     * @Params string $file_url 文件路径

    */

    private function image_to_thumb($file_url, $file_name) {
        $url_thumb = $this->save_dir . 'thumb_' . $file_name;
        $move_url_thumb = $this->save_path . 'thumb_' . $file_name;
        Image::make($file_url)->resize($this->thumb_width, $this->thumb_height)->save($move_url_thumb);
        return $url_thumb;
    }

}

