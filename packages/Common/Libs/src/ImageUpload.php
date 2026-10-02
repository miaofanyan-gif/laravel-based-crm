<?php
/**
 * 图片上传
 */

namespace Common\Libs;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class ImageUpload {

    /**
     * 上传图片到指定目录
     *
     * @param UploadedFile $file
     * @param string $folder 存放的根目录，如 'avatars'
     * @param string $disk 存储磁盘，默认 'public'
     * @return string 返回存储路径（例如 "avatars/2025/01/abc123.jpg"）
     * @throws \Exception
     */
    public function upload(UploadedFile $file, string $folder = 'images', string $disk = 'public'): string
    {
        // 1. 校验是否为有效图片
        if (!$this->isValidImage($file)) {
            throw new \Exception('文件必须是图片类型（jpeg, png, jpg, gif, webp）');
        }

        // 2. 生成唯一文件名（保留原始扩展名）
        $filename = $this->generateUniqueFilename($file);

        // 3. 构建按日期分组的相对路径（例如 images/2025/01/15）
        $relativePath = $this->buildPathWithDate($folder);

        // 4. 存储文件
        $storedPath = Storage::disk($disk)->putFileAs($relativePath, $file, $filename);

        if ($storedPath === false) {
            throw new \Exception('图片存储失败，请检查磁盘权限');
        }

        return $storedPath;
    }

    /**
     * 验证图片 MIME 类型和有效性
     */
    protected function isValidImage(UploadedFile $file): bool
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        return in_array($file->getMimeType(), $allowedMimes) && $file->isValid();
    }

    /**
     * 生成唯一文件名（避免覆盖）
     */
    protected function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        return date('mdHid').Str::random(10) . '.' . $extension;
    }

    /**
     * 按年/月/日自动创建子目录，避免单目录文件过多
     */
    protected function buildPathWithDate(string $folder): string
    {
        return trim($folder, '/') . '/' . date('Y-m/d');
    }

    /**
     * 删除图片
     * @param string $path 存储的相对路径（例如 "images/2025/01/abc.jpg"）
     * @param string $disk 磁盘，默认 'public'
     * @return bool
     */
    public function delete(string $path, string $disk = 'public'): bool
    {
        if (empty($path)) {
            return false;
        }

        $disk = Storage::disk($disk);
        if ($disk->exists($path)) {
            return $disk->delete($path);
        }
        return false;
    }
}

