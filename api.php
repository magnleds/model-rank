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
    $sort = preg_replace('/[^a-z_,]/','', (string)($_GET['sort'] ?? 'req_month'));
    $dir = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $sortMap = ['value'=>'value_score','intelligence'=>'intelligence','req_month'=>'req_month','tps'=>'tps','price'=>'input_price','input'=>'input_price','output'=>'output_price','cache'=>'cache_read_price','budget'=>'budget','cost'=>'cost_per_req','updated'=>'updated_at',];
    // multi-sort: sort=col1,col2 dir=dir1,dir2 (max 2)
    $sortCols = array_values(array_filter(array_map('trim', explode(',', $sort))));
    if (empty($sortCols)) $sortCols = ['req_month'];
    $dirParts = array_map('trim', explode(',', strtolower((string)($_GET['dir'] ?? 'desc'))));
    $orderParts = [];
    foreach(array_slice($sortCols,0,2) as $i=>$sc){
        $sc = preg_replace('/[^a-z_]/','', $sc);
        $col = $sortMap[$sc] ?? 'req_month';
        $d = (isset($dirParts[$i]) && $dirParts[$i]==='asc') ? 'ASC' : ((isset($dirParts[0]) && $dirParts[0]==='asc' && !isset($dirParts[$i])) ? 'ASC' : 'DESC');
        // default dir per column if only one dir given: output/cache asc, others desc
        if (count($dirParts)===1 && $i===1) { $d = in_array($sc,['output','cache','input','price','cost']) ? 'ASC' : 'DESC'; }
        if ($col === 'cache_read_price') $orderParts[] = "COALESCE(cache_read_price,999) $d";
        elseif ($col === 'input_price' || $col === 'output_price') $orderParts[] = "COALESCE($col,999) $d";
        elseif ($col === 'cost_per_req') $orderParts[] = "cost_per_req $d";
        else $orderParts[] = "$col $d";
    }
    // tie-break: intelligence (unless primary), then quota desc (same model cross-platform: bigger quota first)
    $hasIntel = in_array('intelligence', array_slice($sortCols,0,2));
    if (!$hasIntel) $orderParts[] = "intelligence DESC";
    $hasReq = in_array('req_month', array_slice($sortCols,0,2));
    if (!$hasReq) $orderParts[] = "req_month DESC";
    $orderSql = implode(', ', $orderParts);
    $orderCol = $sortMap[preg_replace('/[^a-z_]/','', $sortCols[0])] ?? 'req_month';
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
    $sql = "SELECT *,
        CASE
          WHEN (input_price=0 AND output_price=0) THEN 0.00001
          WHEN budget>0 AND req_month>0 THEN budget*1.0/req_month
          WHEN input_price IS NULL THEN 999999
          ELSE (input_price*800 + output_price*200 + COALESCE(cache_read_price,0)*50000)/1000000
        END as cost_per_req,
        CASE
          WHEN (input_price=0 AND output_price=0) THEN COALESCE(intelligence,25)*1000
          WHEN budget>0 AND req_month>0 THEN MAX(COALESCE(intelligence,0)-30,0) * req_month
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

if ($action === 'usage') {
    // 站长 Go 订阅用量（公开数字，Key 只在服务端 .env）
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip = trim(explode(',', $ip)[0]);
    $now = time();
    // 每日剩余额度预估：(100-已用)/剩余天数 均摊 + 历史快照算真实消耗速度
    $goForecast = function(array $d, int $now) {
        try { db()->exec("CREATE TABLE IF NOT EXISTS usage_snapshots (fetched_at INTEGER PRIMARY KEY, rolling_pct REAL, weekly_pct REAL, monthly_pct REAL, resets_at TEXT)"); } catch (Throwable $e) {}
        $mk = function($w) use ($now) {
            $p = (is_array($w) && isset($w['percent']) && is_numeric($w['percent'])) ? (float)$w['percent'] : null;
            $ts = (is_array($w) && !empty($w['resetsAt'])) ? strtotime($w['resetsAt']) : false;
            if ($p === null || $ts === false || $ts <= $now) return null;
            $daysLeft = ($ts - $now) / 86400;
            if ($daysLeft <= 0) return null;
            $rem = 100 - $p;
            return ['remaining_pct'=>round($rem, 2), 'days_left'=>round($daysLeft, 2),
                'daily_budget'=>round($rem / $daysLeft, 2), 'hourly_budget'=>round($rem / ($daysLeft * 24), 2)];
        };
        $out = ['rolling'=>$mk($d['rolling'] ?? null), 'weekly'=>$mk($d['weekly'] ?? null), 'monthly'=>$mk($d['monthly'] ?? null)];
        // 趋势：同月度窗口内最早快照 → 真实日均消耗 → 预测重置时用量
        try {
            $m = $out['monthly'];
            $curReset = is_array($d['monthly'] ?? null) ? (string)($d['monthly']['resetsAt'] ?? '') : '';
            if ($m && $curReset !== '') {
                $stmt = db()->prepare("SELECT fetched_at, monthly_pct FROM usage_snapshots WHERE resets_at=? AND monthly_pct IS NOT NULL ORDER BY fetched_at ASC LIMIT 1");
                $stmt->execute([$curReset]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && ($now - (int)$row['fetched_at']) > 6*3600) {
                    $elapsed = ($now - (int)$row['fetched_at']) / 86400;
                    $burn = round(((float)($d['monthly']['percent'] ?? 0) - (float)$row['monthly_pct']) / $elapsed, 2);
                    $proj = round((float)($d['monthly']['percent'] ?? 0) + $burn * $m['days_left'], 1);
                    $out['monthly']['daily_burn'] = $burn;
                    $out['monthly']['projected_at_reset'] = $proj;
                    $out['monthly']['verdict'] = $proj >= 100 ? '会超' : ($proj >= 80 ? '偏快' : '够用');
                }
            }
            // 今日口径：今天最早快照 → 当前 = 今日已用；日均预算 − 今日已用 = 今日剩余
            $today = function(string $col, $w, $fc, string $extraWhere, array $extraArgs) use ($now) {
                if (!$fc || !is_array($w) || !isset($w['percent']) || !is_numeric($w['percent'])) return null;
                if ($col !== 'monthly_pct' && $col !== 'weekly_pct') return null;
                $sql = "SELECT fetched_at, $col AS p FROM usage_snapshots WHERE $col IS NOT NULL AND date(fetched_at,'unixepoch','localtime') = date(?,'unixepoch','localtime') $extraWhere ORDER BY fetched_at ASC LIMIT 1";
                $stmt = db()->prepare($sql);
                $stmt->execute(array_merge([$now], $extraArgs));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) return null;
                $used = round((float)$w['percent'] - (float)$row['p'], 2);
                if ($used < 0) $used = 0.0; // 窗口中途重置会归零，负值无意义
                $budget = (float)($fc['daily_budget'] ?? 0);
                return ['used_today'=>$used, 'left_today'=>round($budget - $used, 2),
                    'since'=>date('H:i', (int)$row['fetched_at']),
                    'collecting'=>($now - (int)$row['fetched_at']) < 1800];
            };
            $wm = $out['monthly'];
            if ($wm && $curReset !== '') {
                $t = $today('monthly_pct', $d['monthly'] ?? null, $wm, 'AND resets_at=?', [$curReset]);
                if ($t) $out['monthly']['today'] = $t;
            }
            $ww = $out['weekly'];
            $wTs = (is_array($d['weekly'] ?? null) && !empty($d['weekly']['resetsAt'])) ? strtotime($d['weekly']['resetsAt']) : false;
            if ($ww && $wTs) {
                $t = $today('weekly_pct', $d['weekly'] ?? null, $ww, 'AND fetched_at > ?', [$wTs - 7*86400]);
                if ($t) $out['weekly']['today'] = $t;
            }
            db()->prepare("DELETE FROM usage_snapshots WHERE fetched_at < ?")->execute([$now - 60*86400]);
        } catch (Throwable $e) { /* 预估失败不影响主流程 */ }
        return $out;
    };
    $goSnap = function(array $d, int $now) {
        try {
            $gp = function($w) { return (is_array($w) && isset($w['percent']) && is_numeric($w['percent'])) ? (float)$w['percent'] : null; };
            $reset = is_array($d['monthly'] ?? null) ? (string)($d['monthly']['resetsAt'] ?? '') : '';
            db()->prepare("INSERT OR REPLACE INTO usage_snapshots(fetched_at,rolling_pct,weekly_pct,monthly_pct,resets_at) VALUES(?,?,?,?,?)")
                ->execute([$now, $gp($d['rolling'] ?? null), $gp($d['weekly'] ?? null), $gp($d['monthly'] ?? null), $reset]);
        } catch (Throwable $e) {}
    };
    // 命中 120s 服务端缓存直接返回，不打上游（照样附预估）
    $cacheAt = (int)get_meta('go_usage_at', '0');
    if ($now - $cacheAt < 120) {
        $cached = json_decode(get_meta('go_usage_json', ''), true);
        if (is_array($cached)) { $cached['cached'] = true; $cached['forecast'] = $goForecast($cached, $now); ok($cached); }
    }
    // 限流：同 IP 10 分钟最多 20 次实际查询
    $stmt = db()->prepare("SELECT COUNT(*) FROM refresh_log WHERE ip=? AND type='usage' AND created_at > ?");
    $stmt->execute([$ip, $now - 600]);
    if ((int)$stmt->fetchColumn() >= 20) fail('查询太频繁，稍后再试', 429);
    $ENV = @parse_ini_file(__DIR__ . '/.env') ?: [];
    $goKey = trim($ENV['APP_GO_API_KEY'] ?? '');
    if ($goKey === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $goKey)) fail('服务端未配置 Go Key', 500);
    $goGet = function($url) use ($goKey) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$goKey,'Accept: application/json'], CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>true]);
        $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        return [$code, $body, $err];
    };
    list($uc, $ub, $uerr) = $goGet('https://opencode.ai/zen/go/v1/usage');
    if ($uerr) fail('上游连接失败', 502);
    if ($uc === 401) fail('Go Key 无效或已被删除', 502);
    if ($uc === 403) fail('该 Key 没有 Go 订阅', 502);
    if ($uc !== 200) fail('上游返回 HTTP '.$uc, 502);
    $ud = json_decode($ub, true);
    $u = (isset($ud['usage']) && is_array($ud['usage'])) ? $ud['usage'] : (is_array($ud) ? $ud : []);
    $pick = function($w) { return is_array($w) ? ['percent'=> $w['percent'] ?? null, 'status'=> $w['status'] ?? '-', 'resetsAt'=> $w['resetsAt'] ?? ($w['resetAt'] ?? '')] : ['percent'=>null,'status'=>'-','resetsAt'=>'']; };
    list($mc, $mb) = $goGet('https://opencode.ai/zen/go/v1/models');
    $ids = [];
    $md = json_decode($mb, true);
    if ($mc === 200 && isset($md['data']) && is_array($md['data'])) { foreach ($md['data'] as $it) { if (!empty($it['id'])) $ids[] = $it['id']; } sort($ids); }
    $data = ['rolling'=>$pick(isset($u['rolling']) ? $u['rolling'] : null), 'weekly'=>$pick(isset($u['weekly']) ? $u['weekly'] : null), 'monthly'=>$pick(isset($u['monthly']) ? $u['monthly'] : null), 'models'=>$ids, 'models_count'=>count($ids), 'fetched_at'=>$now, 'cached'=>false];
    $goSnap($data, $now);
    $data['forecast'] = $goForecast($data, $now);
    set_meta('go_usage_json', json_encode($data, JSON_UNESCAPED_UNICODE));
    set_meta('go_usage_at', (string)$now);
    db()->prepare("INSERT INTO refresh_log(ip,type,created_at,success,msg) VALUES(?,?,?,?,?)")->execute([$ip,'usage',$now,1,'ok']);
    ok($data);
}

if ($action === 'recommend') {
    db();
    $min_intel = isset($_GET['min_intel']) && $_GET['min_intel']!=='' ? (float)$_GET['min_intel'] : 35;
    $min_req = isset($_GET['min_req']) && $_GET['min_req']!=='' ? (int)$_GET['min_req'] : 10000;
    $limit = isset($_GET['limit']) && $_GET['limit']!=='' ? max(1, min(50, (int)$_GET['limit'])) : 8;
    // 同族补分：无分模型继承同族已评分版本的分数（is_patch=1）
    $patch = [
        'muse-spark-1-3-contributor'=>48.1, 'muse-spark-1-2-contributor'=>39.6, // 同标准版
        'deepseek-v4-1-flash'=>34.0, 'deepseek-flash'=>34.0,                   // 继承 deepseek-v4-flash
        'mimo-v2-5'=>25.2, 'hy3'=>25.3,
    ];
    $rows = db()->query("SELECT model_id,display_name,intelligence,input_price,output_price,cache_read_price,req_5h,req_week,req_month,budget,source FROM models WHERE source='opencode'")->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $mid = strtolower(str_replace(['.', '_'], '-', (string)$r['model_id']));
        $eff = $r['intelligence'];
        $is_patch = false;
        if (($eff === null || $eff === '') && isset($patch[$mid])) { $eff = $patch[$mid]; $is_patch = true; }
        if ($eff === null || $eff === '') continue;
        if ((float)$eff < $min_intel) continue;
        if ((int)$r['req_month'] < $min_req) continue;
        $r['eff_intel'] = (float)$eff;
        $r['is_patch'] = $is_patch ? 1 : 0;
        $out[] = $r;
    }
    usort($out, function($a, $b) {
        if ((int)$b['req_month'] !== (int)$a['req_month']) return (int)$b['req_month'] <=> (int)$a['req_month'];
        return (float)$b['eff_intel'] <=> (float)$a['eff_intel'];
    });
    $out = array_slice($out, 0, $limit);
    ok(['rows'=>$out, 'min_intel'=>$min_intel, 'min_req'=>$min_req, 'limit'=>$limit, 'fetched_at'=>time()]);
}

if ($action !== '') fail('Unknown action', 404);
}
