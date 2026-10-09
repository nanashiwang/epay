<?php
require '../includes/common.php';
if ($islogin2!=1) { header('Location: login.php'); exit; }
$reviewAdmin=false;
require ROOT.'user/payment_review_view.php';
