<?php
namespace  JoyMedia\WkHtml\Servers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use RuntimeException;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

/**
 * A Laravel wrapper for SnappyImage
 *
 * @package laravel-snappy
 * @author Killian Blais
 */
class HtmlWrapper {

    /**
     * @var string
     */
    public $html;
    public $fileNames;

    /**
     * @var array
     */
    public $temporaryFiles = [];

    /**
     * Load a HTML string
     *
     * @param string $string
     * @return static
     */
    private $defaultExtension = 'png';

    /**
     * 加载框架html
     * @param $view
     * @param $data
     * @return $this
     */
    public function loadView($view, $data = array())
    {
        $this->html = View::make($view, $data)->render();
        $this->generateFromHtml($this->html);
        return $this;
    }

    /**
     * Gets the default extension.
     *
     * @return string
     */
    public function getDefaultExtension(): string
    {
        return $this->defaultExtension;
    }
    /**
     * Sets the default extension.
     * Useful when letting Snappy deal with file creation.
     *
     * @param string $defaultExtension
     *
     * @return $this
     */
    public function setDefaultExtension($defaultExtension)
    {
        $this->defaultExtension = $defaultExtension;
    }
    /**
     * 生成html
     * @param $html
     * @return void
     */
    protected function generateFromHtml($html)
    {
        if (\is_array($html)) {
            foreach ($html as $htmlInput) {
                $this->fileNames[] = $this->createTemporaryFile($htmlInput, 'html');
            }
        } else {
            $this->fileNames[] = $this->createTemporaryFile($html, 'html');
        }
    }


    /**
     * Creates a temporary file.
     * The file is not created if the $content argument is null.
     *
     * @param null|string $content   Optional content for the temporary file
     * @param null|string $extension An optional extension for the filename
     *
     * @return string The filename
     */
    protected function createTemporaryFile($content = null, $extension = null)
    {
        $dir = \rtrim($this->getPhpFolder(), \DIRECTORY_SEPARATOR);
        $pdf_dir = \rtrim($this->getPdfFolder(), \DIRECTORY_SEPARATOR);
        if (!\is_dir($dir)) {
            Log::channel('wkhtml')->info('文件夹是否有权限：'.$dir,[is_dir($dir)]);
            if (false === @\mkdir($dir, 0777, true) && !\is_dir($dir)) {
                throw new \RuntimeException(\sprintf("Unable to create directory: %s\n", $dir));
            }
        } elseif (!\is_writable($dir)) {
            throw new RuntimeException(\sprintf("Unable to write in directory: %s\n", $dir));
        }

        $save_path =  \DIRECTORY_SEPARATOR . \uniqid('bot_html', true);

        $filename = $dir .$save_path;
        $return_filename = $pdf_dir . $save_path;

        if (null !== $extension) {
            $filename .= '.' . $extension;
            $return_filename = $pdf_dir . $save_path.'.' . $extension;
        }

        if (null !== $content) {
            File::put($filename, $content);
        }

        $this->temporaryFiles[] = $filename;
        return $return_filename;
    }


    /**
     * Get TemporaryFolder.
     *
     * @return string
     */
    protected function getPhpFolder()
    {
        return config('wkhtml.config.php_path');
    }

    /**
     * Get getMappingFolder.
     *
     * @return string
     */
    protected function getPdfFolder()
    {
        return config('wkhtml.config.pdf_path');
    }

    /**
     * Removes all temporary files.
     *
     * @return void
     */
    public function removeTemporaryFiles()
    {
        foreach ($this->temporaryFiles as $file) {
            $this->unlink($file);
        }
    }
    /**
     * Wrapper for the "unlink" function.
     *
     * @param string $filename
     *
     * @return bool
     */
    protected function unlink($filename)
    {
        return $this->fileExists($filename) ? \unlink($filename) : false;
    }
    /**
     * Wrapper for the "file_exists" function.
     *
     * @param string $filename
     *
     * @return bool
     */
    protected function fileExists($filename)
    {
        return \file_exists($filename);
    }
}
