<?php
define('APP_ENTRY', true);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fetcher.php';
db();
$last = (int)get_meta('last_auto_refresh','0');
if (time() - $last < 20*3600) { echo "skip: last ".date('Y-m-d H:i',$last)."\n"; exit; }
$res = do_refresh('cron','auto');
db()->prepare("INSERT INTO refresh_log(ip,type,created_at,success,msg) VALUES(?,?,?,?,?)")->execute(['cron','auto',time(),1,json_encode($res,JSON_UNESCAPED_UNICODE)]);
echo "ok ".json_encode($res,JSON_UNESCAPED_UNICODE)."\n";
