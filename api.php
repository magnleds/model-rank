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
    $sort = preg_replace('/[^a-z_,]/','', (string)($_GET['sort'] ?? 'value'));
    $dir = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $sortMap = ['value'=>'value_score','intelligence'=>'intelligence','req_month'=>'req_month','tps'=>'tps','price'=>'input_price','input'=>'input_price','output'=>'output_price','cache'=>'cache_read_price','budget'=>'budget','cost'=>'cost_per_req','updated'=>'updated_at',];
    // multi-sort: sort=col1,col2 dir=dir1,dir2 (max 2)
    $sortCols = array_values(array_filter(array_map('trim', explode(',', $sort))));
    if (empty($sortCols)) $sortCols = ['value'];
    $dirParts = array_map('trim', explode(',', strtolower((string)($_GET['dir'] ?? 'desc'))));
    $orderParts = [];
    foreach(array_slice($sortCols,0,2) as $i=>$sc){
        $sc = preg_replace('/[^a-z_]/','', $sc);
        $col = $sortMap[$sc] ?? 'value_score';
        $d = (isset($dirParts[$i]) && $dirParts[$i]==='asc') ? 'ASC' : ((isset($dirParts[0]) && $dirParts[0]==='asc' && !isset($dirParts[$i])) ? 'ASC' : 'DESC');
        // default dir per column if only one dir given: output/cache asc, others desc
        if (count($dirParts)===1 && $i===1) { $d = in_array($sc,['output','cache','input','price','cost']) ? 'ASC' : 'DESC'; }
        if ($col === 'cache_read_price') $orderParts[] = "COALESCE(cache_read_price,999) $d";
        elseif ($col === 'input_price' || $col === 'output_price') $orderParts[] = "COALESCE($col,999) $d";
        elseif ($col === 'cost_per_req') $orderParts[] = "cost_per_req $d";
        else $orderParts[] = "$col $d";
    }
    // always tie-break by intelligence desc unless already included
    $hasIntel = in_array('intelligence', array_slice($sortCols,0,2));
    if (!$hasIntel) $orderParts[] = "intelligence DESC";
    $orderSql = implode(', ', $orderParts);
    $orderCol = $sortMap[preg_replace('/[^a-z_]/','', $sortCols[0])] ?? 'value_score';
    // multi filters
    $min_intel = isset($_GET['min_intel']) && $_GET['min_intel']!=='' ? (float)$_GET['min_intel'] : null;
    $min_req = isset($_GET['min_req']) && $_GET['min_req']!=='' ? (int)$_GET['min_req'] : null;
    $max_cache = isset($_GET['max_cache']) && $_GET['max_cache']!=='' ? (float)$_GET['max_cache'] : null;
    $max_input = isset($_GET['max_input']) && $_GET['max_input']!=='' ? (float)$_GET['max_input'] : null;
    $max_budget = isset($_GET['max_budget']) && $_GET['max_budget']!=='' ? (float)$_GET['max_budget'] : null;
    $where=[]; $args=[];
    if ($q !== '') { $where[]='(display_name LIKE ? OR model_id LIKE ?)'; $args[]="%$q%"; $args[]="%$q%"; }
    if ($source !== '' && in_array($source, ['opencode','commandcode'])) { $where[]='source=?'; $args[]=$source; }
    if ($only_new) { $where[]='is_new=1'; }
    if ($min_intel!==null) { $where[]='COALESCE(intelligence,0) >= CAST(? AS REAL)'; $args[]=$min_intel; }
    if ($min_req!==null) { $where[]='COALESCE(req_month,0) >= CAST(? AS INTEGER)'; $args[]=$min_req; }
    if ($max_cache!==null) { $where[]='(cache_read_price IS NOT NULL AND cache_read_price <= CAST(? AS REAL))'; $args[]=$max_cache; }
    if ($max_input!==null) { $where[]='(input_price IS NOT NULL AND input_price <= CAST(? AS REAL))'; $args[]=$max_input; }
    if ($max_budget!==null) { $where[]='(budget IS NOT NULL AND budget <= CAST(? AS REAL))'; $args[]=$max_budget; }
    $whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';
    if ($orderCol === 'cache_read_price') $orderSql = "COALESCE(cache_read_price,999) $dir";
    elseif ($orderCol === 'input_price' || $orderCol === 'output_price') $orderSql = "COALESCE($orderCol,999) $dir";
    elseif ($orderCol === 'cost_per_req') $orderSql = "cost_per_req $dir";
    else $orderSql = "$orderCol $dir";
    $sql = "SELECT *,
        CASE
          WHEN (input_price=0 AND output_price=0) THEN 0.00001
          WHEN budget>0 AND req_month>0 THEN budget*1.0/req_month
          WHEN input_price IS NULL THEN 999999
          ELSE (input_price*800 + output_price*200 + COALESCE(cache_read_price,0)*50000)/1000000
        END as cost_per_req,
        CASE
          WHEN (input_price=0 AND output_price=0) THEN COALESCE(intelligence,25)*1000
          WHEN budget>0 AND req_month>0 THEN MAX(COALESCE(intelligence,0)-30,0) * req_month *1.0 / budget
          WHEN input_price IS NULL THEN 0
          ELSE MAX(COALESCE(intelligence,0)-30,0) / ((input_price*800 + output_price*200 + COALESCE(cache_read_price,0)*50000)/1000000 + 0.0001)
        END as value_score
        FROM models $whereSql ORDER BY $orderSql";
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $now = time();
    foreach ($rows as &$r) {
        $r['is_new_calc'] = ($now - (int)$r['first_seen_at']) < 7*86400 ? 1 : 0;
        $r['days_old'] = (int)floor(($now - (int)$r['first_seen_at'])/86400);
    }
    $meta_last = max((int)get_meta('last_auto_refresh','0'), (int)get_meta('last_manual_refresh','0'));
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
