<?php
require '../includes/common.php';
if ($islogin!=1) { header('Location: login.php'); exit; }
$reviewAdmin=true;
require ROOT.'user/payment_review_view.php';
