<?php
/**
 * @date    04.03.2015
 * @version 1.3
 * @author  Aleksandr Milenin admin@azrr.info
 * @site    https://github.com/azurre/php-simple-file-uploader
 */

class Uploader {
    //! Maximum try count to find available name for uploaded file
    const NAME_TRY_COUNT = 10;

    const
        ERROR_NO_ERROR          = 0,
        ERROR_INI_SIZE          = 1,
        ERROR_FORM_SIZE         = 2,
        ERROR_PARTIAL           = 3,
        ERROR_NO_FILE           = 4,
        ERROR_NO_TMP_DIR        = 6,
        ERROR_CANNOT_WRITE      = 7,
        ERROR_EXTENSION         = 8,
        ERROR_FILE_TOO_LARGE    = 20,
        ERROR_INVALID_MIMETYPE  = 21,
        ERROR_INVALID_EXTENSION = 22,
        ERROR_INVALID_FORMATTER = 23,
        ERROR_NO_AVAILABLE_NAME = 24,
        ERROR_CANNOT_GET_FILE   = 25,
        ERROR_CANNOT_MOVE_FILE  = 26,

        VALIDATOR_MIME      = 0,
        VALIDATOR_SIZE      = 1,
        VALIDATOR_EXTENSION = 2,

        NAME_FORMAT_ORIGINAL = 0,
        NAME_FORMAT_RANDOM   = 1,
        NAME_FORMAT_COMBINED = 2;

    protected $errorMessages = array(
        self::ERROR_NO_ERROR          => 'No errors',
        self::ERROR_INI_SIZE          => 'File size exceeds the upload_max_filesize in php.ini',
        self::ERROR_FORM_SIZE         => 'File size exceeds the html form MAX_FILE_SIZE directive',
        self::ERROR_PARTIAL           => 'The uploaded file was only partially uploaded',
        self::ERROR_NO_FILE           => 'No file was uploaded',
        self::ERROR_NO_TMP_DIR        => 'Missing a temporary folder',
        self::ERROR_CANNOT_WRITE      => 'Failed to write file to disk',
        self::ERROR_EXTENSION         => 'A PHP extension stopped the file upload',
        self::ERROR_INVALID_EXTENSION => 'Validator: restricted extension',
        self::ERROR_INVALID_MIMETYPE  => 'Validator: restricted mime-type',
        self::ERROR_FILE_TOO_LARGE    => 'Validator: file too large',
        self::ERROR_INVALID_FORMATTER => 'Formatter function must return filename',
        self::ERROR_NO_AVAILABLE_NAME => 'Cannot find available name for uploaded file',
        self::ERROR_CANNOT_GET_FILE   => 'Cannot get file content from URL',
        self::ERROR_CANNOT_MOVE_FILE  => 'Cannot move uploaded file'
    );


    /**
     * Type of filename generation
     *
     * @var int
     */
    protected $nameFormat = self::NAME_FORMAT_COMBINED;

    /**
     * Overwrite existing files?
     *
     * @var bool
     */
    protected $overwrite = false;

    /**
     * Transliterate cyrillic names
     *
     * @var bool
     */
    protected $replaceCyrillic = true;

    /**
     * Path to storage
     *
     * @var string
     */
    protected $storagePath = './';

    protected
        $beforeValidateCallback,
        $afterValidateCallback,
        $beforeUploadCallback,
        $afterUploadCallback,
        $nameFormatter,
        $isUrlUpload = false,
        $files = array(),
        $validators = array(),
        $errorCode = self::ERROR_NO_ERROR;


    /**
     * Uploader constructor.
     */
    public function __construct()
    {
        // fix: http://stackoverflow.com/questions/4451664/make-php-pathinfo-return-the-correct-filename-if-the-filename-is-utf-8
        setlocale(LC_ALL, 'en_US.UTF-8');
    }

    /**
     * Transliterate cyrillic names. Strongly recommended!
     *
     * @param bool $replace
     * @return $this
     */
    public function setReplaceCyrillic($replace = true)
    {
        $this->replaceCyrillic = (bool)$replace;
        return $this;
    }

    /**
     * @return int
     */
    public function getNameFormat()
    {
        return $this->nameFormat;
    }

    /**
     * Define naming format
     *
     * @param int $nameFormat
     * @return $this
     */
    public function setNameFormat($nameFormat)
    {
        $this->nameFormat = (int)$nameFormat;
        return $this;
    }

    /**
     * Retrieve error message
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessages[ $this->errorCode ];
    }

    /**
     * Retrieve error code
     *
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }


    /**
     * @param int  $errorCode
     * @param bool $throwException
     *
     * @return $this
     * @throws \Exception
     */
    public function setError($errorCode, $throwException = true)
    {
        $this->errorCode = (int)$errorCode;
        if ($throwException) {
            throw new \Exception($this->getErrorMessage(), $this->errorCode);
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function clearErrorCode()
    {
        $this->errorCode = static::ERROR_NO_ERROR;
        return $this;
    }

    /**
     * @param bool $overwrite
     *
     * @return $this
     */
    public function setOverwrite($overwrite = true)
    {
        $this->overwrite = (bool)$overwrite;
        return $this;
    }

    /**
     * @param string $storagePath
     *
     * @return $this
     */
    public function setStoragePath($storagePath)
    {
        $this->storagePath = $storagePath;
        return $this;
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }


    /**
     * @param int   $validatorType
     * @param mixed $data
     * @return $this
     * @throws \Exception
     */
    public function addValidator($validatorType, $data)
    {
        if (!is_int($validatorType) || $validatorType < 0 || $validatorType > 2) {
            throw new \Exception('Invalid validator type');
        }
        $this->validators[] = array('type' => $validatorType, 'data' => $data);
        return $this;
    }

    /**
     * @param array $file
     * @throws \Exception
     */
    public function applyValidators($file)
    {
        foreach ($this->validators as $validator) {
            switch ($validator['type']) {
                case self::VALIDATOR_MIME:
                    $this->validateMimetype($file['mime'], $validator['data']);
                    break;

                case self::VALIDATOR_SIZE:
                    $this->validateSize($file['size'], $validator['data']);
                    break;

                case self::VALIDATOR_EXTENSION:
                    $this->validateExtension($file['extension'], $validator['data']);
                    break;

            }
        }
    }

    /**
     * @param string $mimetype
     * @param array  $allowedMimetypes
     * @throws \Exception
     */
    public function validateMimetype($mimetype, $allowedMimetypes)
    {
        $allowedMimetypes = is_array($allowedMimetypes) ? $allowedMimetypes : array($allowedMimetypes);
        if (!in_array($mimetype, $allowedMimetypes)) {
            $this->setError(static::ERROR_INVALID_MIMETYPE . ": {$mimetype}");
        }
    }

    /**
     * @param int|string $size Support human readable size e.g. "200K", "1M"
     * @param int        $maxSize
     * @throws \Exception
     */
    public function validateSize($size, $maxSize)
    {
        $maxSize = static::humanReadableToBytes($maxSize);
        if ($size > $maxSize) {
            $this->setError(static::ERROR_FILE_TOO_LARGE);
        }
    }

    /**
     * @param string $extension
     * @param array  $allowedExtensions
     * @throws \Exception
     */
    public function validateExtension($extension, $allowedExtensions)
    {
        $extension         = strtolower($extension);
        $allowedExtensions = is_array($allowedExtensions) ? $allowedExtensions : array($allowedExtensions);
        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception("Extension {$extension} not allowed");
        }
    }

    /**
     * @return string
     */
    public function getDestination()
    {
        return $this->storagePath;
    }

    /**
     * @param array $file
     *
     * @return string
     * @throws \Exception
     */
    protected function getName($file)
    {
        if (!empty($this->nameFormatter)) {
            $newName = $this->applyCallback($this->nameFormatter, $file);
            if (empty($newName) || !is_string($newName)) {
                $this->setError(static::ERROR_INVALID_FORMATTER);
            }

            return $newName;
        }

        if ($this->getNameFormat() === static::NAME_FORMAT_ORIGINAL) {
            return $file['fullName'];
        }

        $tryCount = 0;
        $prefix   = '';
        while ($tryCount < static::NAME_TRY_COUNT) {
            $tryCount++;
            if ($this->getNameFormat() === static::NAME_FORMAT_COMBINED) {
                $prefix = ($this->replaceCyrillic ? static::transliterate($file['name']) : $file['name']) . '_';
            }

            $newName = $prefix . uniqid() . '.' . $file['extension'];

            $path = $this->getDestination() . $newName;
            if (!file_exists($path)) {
                return $newName;
            }
        }

        $this->setError(static::ERROR_NO_AVAILABLE_NAME);
        return 'error'; // IDE fix :(
    }


    /**
     * @param string $key Key of $_FILE array
     * @throws \Exception
     */
    public function upload($key)
    {
        $this->isUrlUpload = false;
        $this->clearErrorCode();

        if (!isset($_FILES[ $key ])) {
            throw new \Exception("Cannot find uploaded file(s) with key: {$key}");
        }

        if (is_array($_FILES[ $key ]['tmp_name'])) {
            foreach ($_FILES[ $key ]['name'] as $idx => $name) {
                $this->files[ $idx ] = array(
                    'name'      => pathinfo($name, PATHINFO_FILENAME),
                    'fullName'  => $name,
                    'newName'   => $name,
                    'fullPath'  => '',
                    'extension' => pathinfo($name, PATHINFO_EXTENSION),
                    'mime'      => $_FILES[ $key ]['type'][ $idx ],
                    'tmpName'   => $_FILES[ $key ]['tmp_name'][ $idx ],
                    'size'      => $_FILES[ $key ]['size'][ $idx ],
                    'error'     => $_FILES[ $key ]['error'][ $idx ]
                );
            }
        } else {
            $this->files[0] = array(
                'name'      => pathinfo($_FILES[ $key ]['name'], PATHINFO_FILENAME),
                'fullName'  => $_FILES[ $key ]['name'],
                'newName'   => $_FILES[ $key ]['name'],
                'fullPath'  => '',
                'extension' => pathinfo($_FILES[ $key ]['name'], PATHINFO_EXTENSION),
                'mime'      => $_FILES[ $key ]['type'],
                'tmpName'   => $_FILES[ $key ]['tmp_name'],
                'size'      => $_FILES[ $key ]['size'],
                'error'     => $_FILES[ $key ]['error']
            );
        }

        $this->process();
    }


    protected function process()
    {
        foreach ($this->files as $key => &$file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->errorCode = $file['error'];
                continue;
            }

            $this->applyCallback($this->beforeValidateCallback, $file);
            $this->applyValidators($file);
            $this->applyCallback($this->afterValidateCallback, $file);


            $file['newName'] = $this->getName($file);

            $destinationFile = $this->getDestination() . $file['newName'];

            if (!$this->overwrite && file_exists($destinationFile)) {
                throw new \Exception("File {$file['name']} already exists");
            }

            $this->applyCallback($this->beforeUploadCallback, $file);
            if ($this->isUrlUpload) {
                if (@rename($file['tmpName'], $destinationFile) === false) {
                    $this->setError(static::ERROR_CANNOT_MOVE_FILE);
                }
                chmod($destinationFile, 0644);
            } else {
                if (@move_uploaded_file($file['tmpName'], $destinationFile) === false) {
                    $this->setError(static::ERROR_CANNOT_MOVE_FILE);
                }
            }
            $file['fullPath'] = realpath($destinationFile);
            $this->applyCallback($this->afterUploadCallback, $file);
        }
    }

    /**
     * @param string $url File url
     *
     * @throws \Exception
     */
    public function uploadByUrl($url)
    {
        $this->isUrlUpload = true;
        $this->clearErrorCode();

        $urlInfo  = pathinfo($url);
        $basename = empty($urlInfo['basename']) ? 'noname' : $urlInfo['basename'];
        $basename = preg_replace('/[^\w\.\-]/', '', $basename);
        $tempFile = tempnam(sys_get_temp_dir(), 'upload');

        $this->files    = [];
        $this->files[0] = array(
            'name'      => isset($urlInfo['filename']) ? $urlInfo['filename'] : 'noname',
            'fullName'  => $basename,
            'newName'   => isset($urlInfo['basename']) ? $urlInfo['basename'] : 'noname',
            'fullPath'  => '',
            'extension' => isset($urlInfo['extension']) ? $urlInfo['extension'] : '',
            'mime'      => 'application/octet-stream',
            'tmpName'   => $tempFile,
            'size'      => 0,
            'error'     => $tempFile ? static::ERROR_NO_ERROR : static::ERROR_NO_TMP_DIR
        );

        //@todo maxFileSize
        if (!$content = @file_get_contents($url)) {
            $this->files[0]['error'] = static::ERROR_CANNOT_GET_FILE;

            return;
        }

        $this->files[0]['size'] = strlen($content);

        if (!@file_put_contents($tempFile, $content)) {
            $this->files[0]['error'] = static::ERROR_CANNOT_WRITE;

            return;
        }
        $this->files[0]['mime'] = mime_content_type($tempFile);

        $this->process();
    }


    /**
     * @param callable $callback
     * @return $this
     */
    public function beforeValidate($callback)
    {
        $this->beforeValidateCallback = $callback;
        return $this;
    }

    /**
     * @param callable $callback
     *
     * @return $this
     */
    public function afterValidate($callback)
    {
        $this->afterValidateCallback = $callback;
        return $this;
    }

    /**
     * @param callable $callback
     *
     * @return $this
     */
    public function beforeUpload($callback)
    {
        $this->beforeUploadCallback = $callback;
        return $this;
    }

    /**
     * @param callable $callback
     *
     * @return $this
     */
    public function afterUpload($callback)
    {
        $this->afterUploadCallback = $callback;
        return $this;
    }

    /**
     * Set new filename formatter function
     *
     * @param callable $nameFormatter
     *
     * @return $this
     */
    public function setNameFormatter($nameFormatter)
    {
        $this->nameFormatter = $nameFormatter;
        return $this;
    }

    /**
     * Apply callable
     *
     * @param  callable $callback
     * @param  array    $file
     *
     * @return mixed
     */
    protected function applyCallback($callback, $file)
    {
        if (is_callable($callback)) {
            return call_user_func_array($callback, array($file, $this));
        }
        return false;
    }

    /**
     * Convert human readable size into bytes
     *
     * @param  string $input
     * @return int
     */
    public static function humanReadableToBytes($input)
    {
        if (is_numeric($input)) {
            return (int)$input;
        }
        $number = (int)$input;
        $units  = array(
            'b' => 1,
            'k' => 1024,
            'm' => 1048576,
            'g' => 1073741824
        );
        $unit   = strtolower(substr($input, -1));
        if (isset($units[ $unit ])) {
            $number = $number * $units[ $unit ];
        }
        return $number;
    }

    /**
     * @param string $string
     * @return string
     */
    public static function transliterate($string)
    {
        $roman    = array("Sch", "sch", 'Yo', 'Zh', 'Kh', 'Ts', 'Ch', 'Sh', 'Yu', 'ya', 'yo', 'zh', 'kh', 'ts', 'ch', 'sh', 'yu', 'ya', 'A', 'B', 'V', 'G', 'D', 'E', 'Z', 'I', 'Y', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'F', '', 'Y', '', 'E', 'a', 'b', 'v', 'g', 'd', 'e', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', '', 'y', '', 'e');
        $cyrillic = array("Щ", "щ", 'Ё', 'Ж', 'Х', 'Ц', 'Ч', 'Ш', 'Ю', 'я', 'ё', 'ж', 'х', 'ц', 'ч', 'ш', 'ю', 'я', 'А', 'Б', 'В', 'Г', 'Д', 'Е', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Ь', 'Ы', 'Ъ', 'Э', 'а', 'б', 'в', 'г', 'д', 'е', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'ь', 'ы', 'ъ', 'э');
        return str_replace($cyrillic, $roman, $string);
    }
}
