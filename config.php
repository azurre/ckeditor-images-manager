<?php
/**
 * @date    07.10.2016
 * @version 0.1
 * @author  Aleksandr Milenin azrr.mail@gmail.com
 */

return array(
    'auth'          => true,
    'authUrl'       => 'check-auth.php',
    'imageFullUrl'  => false,
    'uploadDir'     => 'upload/',
    'uploadPath'    => dirname(__FILE__) . '/upload/',
    'maxSize'       => '1M',
    'allowExt'      => array('png', 'jpg', 'jpeg', 'gif', 'webp'),
    'allowMime'     => array('image/png', 'image/jpeg', 'image/gif', 'image/webp')
);