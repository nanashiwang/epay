<?php
namespace lib {
    class MsgNotice {
        public static function subscriptionReminder($u,$g,$n) {
            echo "MAIL\n"; fflush(STDOUT);
            if (getenv('EPAY_TEST_BLOCK_MAIL')) fgets(STDIN);
            return true;
        }
    }
}
namespace {
    require __DIR__.'/bepusdt-bootstrap.php';
    $name=$argv[1]??'';
    if (!in_array($name,['collection','subscription'],true)) exit(1);
    $argv=['worker','--once'];
    require ROOT.'scripts/'.$name.'-worker.php';
}
