<?php
/**
 * @date    07.10.2016
 * @version 0.1
 * @author  Aleksandr Milenin azrr.mail@gmail.com
 */

define('BASE_PATH', dirname(__FILE__) . '/');

class Api {

    protected $debug;

    public $config = array();

    public function __construct()
    {
        $this->config = require BASE_PATH . 'config.php';
    }

    public function run()
    {
        if (!$this->auth()) {
            $this->response(
                array(
                    'error' => 1,
                    'debug' => $this->getDebug()
                )
            );
        }

        $action = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
        switch ($action) {
            default:
                $this->response(array('error' => 100));
                break;

            case 'get-images':
                header('Content-Type: application/json');
                exit(json_encode(array(
                    'error'        => 0,
                    'dir'          => $this->config['uploadDir'],
                    'imageFullUrl' => $this->config['imageFullUrl'],
                    'list'         => $this->getImages()
                )));
                break;

            case 'upload':
                require BASE_PATH . 'Uploader.php';

                $error    = 0;
                $files    = array();
                $Uploader = new Uploader();
                $Uploader
                    ->setStoragePath($this->config['uploadPath'])
                    ->addValidator(Uploader::VALIDATOR_MIME, $this->config['allowMime'])
                    ->addValidator(Uploader::VALIDATOR_EXTENSION, $this->config['allowExt'])
                    ->addValidator(Uploader::VALIDATOR_SIZE, $this->config['maxSize']);

                try {
                    $Uploader->upload('file');
                    $files = $Uploader->getFiles();
                } catch (\Exception $e) {
                    $error = $e->getMessage();
                }

                foreach ($files as &$file) {
                    $file = static::getImageInfo($file['fullPath']);
                }

                header('Content-Type: application/json');
                exit(json_encode(array(
                    'error' => $error,
                    'list'  => $files
                )));
                break;

            case 'delete':
                if ($success = $this->delete($_REQUEST['file'])) {
                    $this->response(array('error' => 0));
                }

                $this->response(array('error' => $this->debug['message'], 'debug' => $this->debug));
                break;
        }
    }


    public function getImages()
    {
        $path   = $this->config['uploadPath'];
        $images = glob("{$path}*.{jpg,jpe,jpeg,png,gif}", GLOB_BRACE);
        if (empty($images)) {
            return array();
        }

        foreach ($images as &$image) {
            $image = static::getImageInfo($image);
        }

        usort($images, function ($a, $b) { return $a['date'] - $b['date']; });

        return $images;
    }

    /**
     * @param string $image Full path to image
     *
     * @return array|bool
     */
    public static function getImageInfo($image)
    {
        if (!is_file($image) || !is_readable($image)) {
            return false;
        }

        $info = pathinfo($image);
        list($width, $height, ,) = getimagesize($image);

        return array(
            'name'   => $info['basename'],
            'ext'    => $info['extension'],
            'date'   => filemtime($image),
            'size'   => filesize($image),
            'width'  => $width,
            'height' => $height
        );
    }

    public function delete($filename)
    {
        if (!$filePath = realpath($this->config['uploadPath'] . $filename)) {
            $this->debug = ['message' => 'Cannot get realpath'];

            return false;
        }

        $configUploadPath = realpath($this->config['uploadPath']);

        if (dirname($filePath) !== $configUploadPath) {
            $this->debug = ['message' => 'Delete path not secure: ' . dirname($filePath) . '/ !=' . $configUploadPath];

            return false;
        }

        return unlink($filePath);
    }

    public function auth()
    {
        $cookies = array();
        foreach ($_COOKIE as $key => $value) {
            $cookies[] = "{$key}={$value}";
        }

        $opts    = array(
            'http' => array(
                'method' => 'GET',
                'header' => 'Cookie: ' . implode('; ', $cookies) . "\r\n"
            )
        );
        $context = stream_context_create($opts);

        $authUrl = $this->config['authUrl'];
        if (substr($authUrl, 0, 4) !== 'http') {
            $dir     = dirname($_SERVER['REQUEST_URI']);
            $authUrl = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}{$dir}/{$this->config['authUrl']}";
        }

        if (!$result = file_get_contents($authUrl, false, $context)) {
            $this->debug = ['result' => $result, 'url' => $authUrl];

            return false;
        }

        if (!$response = @json_decode($result)) {
            $this->debug = ['result' => $response];

            return false;
        }

        $this->debug = $response;
        if (isset($response->error) && $response->error == 0) {
            return true;
        }

        return false;
    }

    public function getDebug()
    {
        return $this->debug;
    }

    public function response($response)
    {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

}

/********* Start *******/
$Api = new Api();
$Api->run();
