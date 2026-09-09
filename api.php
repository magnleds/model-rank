<?php
define('APP_ENTRY', true);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fetcher.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 仅当直接访问 api.php 时才执行路由；被 include 时跳过（供 cron 复用 do_refresh）
$isDirect = basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'api.php';

if ($isDirect) {
if ($action === 'list_models') {
    db();
    $q = trim((string)($_GET['q'] ?? ''));
    $source = trim((string)($_GET['source'] ?? ''));
    $only_new = (string)($_GET['only_new'] ?? '') === '1';
    $sort = preg_replace('/[^a-z_]/','', (string)($_GET['sort'] ?? 'value'));
    $dir = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $sortMap = ['value'=>'value_score','intelligence'=>'intelligence','req_month'=>'req_month','tps'=>'tps','price'=>'input_price','cache'=>'cache_read_price','updated'=>'updated_at',];
    $orderCol = $sortMap[$sort] ?? 'value_score';
    $where=[]; $args=[];
    if ($q !== '') { $where[]='(display_name LIKE ? OR model_id LIKE ?)'; $args[]="%$q%"; $args[]="%$q%"; }
    if ($source !== '' && in_array($source, ['opencode','commandcode'])) { $where[]='source=?'; $args[]=$source; }
    if ($only_new) { $where[]='is_new=1'; }
    $whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';
    $orderSql = ($orderCol === 'cache_read_price') ? "COALESCE(cache_read_price,999) $dir" : "$orderCol $dir";
    $sql = "SELECT *, 
        CASE WHEN intelligence IS NULL THEN 0 ELSE intelligence END as intel_coalesce,
        CASE 
          WHEN input_price IS NULL THEN 999999
          WHEN (input_price=0 AND output_price=0) THEN 0.00001
          ELSE (input_price*800 + output_price*200 + cache_read_price*50000)/1000000
        END as cost_per_req,
        CASE
          WHEN input_price IS NULL THEN COALESCE(intelligence,0) * req_month / 60000.0
          WHEN (input_price=0 AND output_price=0) THEN COALESCE(intelligence,25)*1000
          ELSE COALESCE(intelligence,0) / ((input_price*800 + output_price*200 + cache_read_price*50000)/1000000 + 0.0001)
        END as value_score
        FROM models $whereSql ORDER BY $orderSql, intelligence DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $now = time();
    foreach ($rows as &$r) {
        $r['is_new_calc'] = ($now - (int)$r['first_seen_at']) < 7*86400 ? 1 : 0;
        $r['days_old'] = (int)floor(($now - (int)$r['first_seen_at'])/86400);
    }
    $meta_last = get_meta('last_auto_refresh','0');
    $total = (int)db()->query("SELECT COUNT(*) FROM models")->fetchColumn();
    $op = (int)db()->query("SELECT COUNT(*) FROM models WHERE source='opencode'")->fetchColumn();
    $cc = (int)db()->query("SELECT COUNT(*) FROM models WHERE source='commandcode'")->fetchColumn();
    $newc = (int)db()->query("SELECT COUNT(*) FROM models WHERE first_seen_at > ".(time()-7*86400))->fetchColumn();
    ok(['rows'=>$rows,'total'=>$total,'opencode'=>$op,'commandcode'=>$cc,'new_count'=>$newc,'last_refresh'=> (int)$meta_last, 'rows_filtered'=>count($rows)]);
}

if ($action === 'refresh') {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip = explode(',', $ip)[0]; $ip = trim($ip);
    $now = time();
    $stmt = db()->prepare("SELECT COUNT(*) FROM refresh_log WHERE ip=? AND date(created_at,'unixepoch','localtime') = date('now','localtime') AND type='manual'");
    $stmt->execute([$ip]);
    $cnt = (int)$stmt->fetchColumn();
    if ($cnt >= 5) fail('今日手动刷新已达上限（5次），请明天再试', 429);
    try {
        $result = do_refresh($ip, 'manual');
        db()->prepare("INSERT INTO refresh_log(ip,type,created_at,success,msg) VALUES(?,?,?,?,?)")->execute([$ip,'manual',$now,1, json_encode($result, JSON_UNESCAPED_UNICODE)]);
        set_meta('last_manual_refresh', (string)$now);
        ok($result);
    } catch (Throwable $e) {
        db()->prepare("INSERT INTO refresh_log(ip,type,created_at,success,msg) VALUES(?,?,?,?,?)")->execute([$ip,'manual',$now,0,$e->getMessage()]);
        fail('刷新失败: '.$e->getMessage(), 500);
    }
}

if ($action === 'stats') {
    $total = (int)db()->query("SELECT COUNT(*) FROM models")->fetchColumn();
    ok(['total'=>$total, 'last_auto'=>get_meta('last_auto_refresh','0')]);
}

if ($action !== '') fail('Unknown action', 404);
}
