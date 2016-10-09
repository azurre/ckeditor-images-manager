<?php

//use App\Models\Session;
//use App\Models\User;
//
//require(dirname(__FILE__) . '/bootstrap.php');
//
//$error   = 1;
//$Session = Session::getInstance();
//if (!empty($_COOKIE['sid'])) {
//    $userID = $Session->getUserID($_COOKIE['sid']);
//
//    if (User::isAdmin($userID)) {
//        $error = 0;
//    }
//}

$error = 0;

header('Content-Type: application/json');
echo json_encode(array('error' => $error));
exit;