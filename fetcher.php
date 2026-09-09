<?php
function http_get(string $url, int $timeout=12): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_TIMEOUT=>$timeout,
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_USERAGENT=>'ModelRank/1.0',
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_HTTPHEADER=>['Accept: text/html,application/json'],
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res===false || $code>=400) return null;
    return $res;
}
function normalize_id(string $id): string {
    return strtolower(str_replace(['.', '_'], '-', $id));
}
$GOAT_BUDGETS = [
  "tencent/hy4-preview"=>20, "tencent/hy3-paid"=>70, "kimi-k3"=>20, "kimi-k2.7-code"=>60, "kimi-k2.7-code-highspeed"=>20, "kimi-k2.6"=>20, "kimi-k2.5"=>20,
  "glm-5.3-flash"=>40, "glm-5.3"=>20, "glm-5.2"=>70, "glm-5.2-fast"=>20, "glm-5.1"=>20, "glm-5"=>20,
  "minimax-m3"=>47, "minimax-m2.7"=>20, "minimax-m2.5"=>20, "deepseek-v4-flash-fast"=>20, "qwen-3.8-max-0902"=>20, "qwen-3.8-max"=>20, "qwen-3.8-27b"=>70,
  "qwen-3.6-max"=>20, "qwen-3.7-max"=>33, "qwen-3.8-flash"=>20, "step-3.7-flash"=>20, "step-3.5-flash"=>20,
  "mimo-v2.5-pro"=>20, "mimo-v2.5"=>30, "nemotron-3-ultra"=>20, "inkling-small"=>20,
  "claude-fable-5-1"=>20, "claude-fable-5"=>20, "claude-opus-5"=>20, "claude-opus-4-8"=>20, "claude-opus-4-7"=>20, "claude-opus-4-6"=>20, "claude-sonnet-5"=>20, "claude-sonnet-4-6"=>20, "claude-haiku-4-5"=>20,
  "gpt-5.5"=>20, "gpt-5.4"=>20, "gpt-5.4-mini"=>20, "gpt-5.3-codex"=>20,
  "gemini-3.8-flash"=>40, "gemini-3.7-flash"=>40, "gemini-3.6-flash"=>20, "gemini-3.5-flash"=>20, "gemini-3.5-flash-lite"=>20, "gemini-3.1-flash-lite"=>20, "fugu-ultra"=>20,
  "muse-spark-1.1"=>20, "muse-spark-1.2"=>20, "muse-spark-1.2-contributor"=>20, "muse-spark-1.3"=>20, "muse-spark-1.3-contributor"=>20, "grok-4.5"=>20,
  "deepseek-v4-pro"=>20, "deepseek-v4-flash"=>20, "deepseek-v4-flash-vision-exp"=>20,
  "qwen-3.7-plus"=>20, "qwen-3.6-plus"=>20, "qwen-3.7-flash"=>20, "grok-4-6"=>20, "hy4-preview"=>20, "tencent-hy3"=>70, "inkling"=>20,
];
function goat_budget(string $mid): int {
  global $GOAT_BUDGETS;
  $low = strtolower($mid);
  if (isset($GOAT_BUDGETS[$low])) return $GOAT_BUDGETS[$low];
  $norm = normalize_id($low);
  if (isset($GOAT_BUDGETS[$norm])) return $GOAT_BUDGETS[$norm];
  $parts = explode('/', $low);
  $base = strtolower(str_replace(['.', '_'], '-', end($parts)));
  if (isset($GOAT_BUDGETS[$base])) return $GOAT_BUDGETS[$base];
  foreach($GOAT_BUDGETS as $k=>$v){
    $kb = normalize_id(explode('/', $k)[count(explode('/', $k))-1]);
    if ($kb === $norm || $kb === $base) return $v;
  }
  return 20;
}
function parse_opencode(): array {
    $pricing = [
        'grok-4.6'=>[169,423,845],
        'grok-4-5'=>[169,423,845],
        'mimo-v2-pro'=>[3250,8150,16300],
        'mimo-v2-omni'=>[3250,8150,16300],
        'minimax-m2-5'=>[3400,8500,17000],
        'qwen3-5-plus'=>[3300,8200,16300],
        'mimo-v2-5'=>[30100,75200,150400],
        'gpt-5.6-luna'=>[2050,5100,10250],
        'glm-5.3-flash'=>[1580,3950,7900],
        'glm-5.3'=>[220,540,1080],
        'glm-5.2'=>[880,2150,4300],
        'glm-5.1'=>[880,2150,4300],
        'glm-5'=>[880,2150,4300],
        'kimi-k3'=>[110,250,490],
        'kimi-k2.7-code'=>[1350,3380,6750],
        'kimi-k2.6'=>[1150,2880,5750],
        'longcat-2.0'=>[11400,28600,57200],
        'mimo-v2.5'=>[30100,75200,150400],
        'mimo-v2.5-pro'=>[3250,8150,16300],
        'minimax-m3'=>[3200,8000,16000],
        'minimax-m2.7'=>[3400,8500,17000],
        'muse-spark-1.3-contributor'=>[45300,113300,226600],
        'muse-spark-1-2-contributor'=>[45300,113300,226600],
        'muse-spark-1.2-contributor'=>[45300,113300,226600],
        'qwen3.8-max'=>[160,400,810],
        'qwen3.8-flash'=>[5400,13500,27000],
        'qwen3.7-max'=>[170,420,840],
        'qwen3.7-plus'=>[4300,10800,21600],
        'qwen3.6-plus'=>[3300,8200,16300],
        'deepseek-v4-pro'=>[1050,2600,5200],
        'deepseek-v4-flash'=>[7600,18900,37800],
        'deepseek-v4-flash-vision-exp'=>[3800,9450,18900],
        'hy4-preview'=>[1350,3380,6770],
        'hy3'=>[4300,10750,21500],
        'omen-alpha'=>[11600,29000,57900],
    ];
    $json = http_get('https://opencode.ai/zen/go/v1/models');
    $ids = [];
    if ($json) {
        $data = json_decode($json, true);
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $m) if (!empty($m['id'])) $ids[] = strtolower($m['id']);
        }
    }
    if (empty($ids)) $ids = array_keys($pricing);
    // live docs parse for pricing
    $html = http_get('https://opencode.ai/docs/go');
    if ($html) {
        if (preg_match_all('/<td[^>]*>\s*([a-z0-9\.\-]+)\s*<\/td>\s*<td[^>]*>\s*([\d,]+)\s*<\/td>\s*<td[^>]*>\s*([\d,]+)\s*<\/td>\s*<td[^>]*>\s*([\d,]+)\s*<\/td>/i', $html, $mm)) {
            foreach ($mm[1] as $i=>$mid) {
                $mid = normalize_id(trim($mid));
                if (isset($pricing[$mid])) {
                    $pricing[$mid] = [(int)str_replace(',','',$mm[2][$i]), (int)str_replace(',','',$mm[3][$i]), (int)str_replace(',','',$mm[4][$i])];
                } else {
                    // add unknown
                    $pricing[$mid] = [(int)str_replace(',','',$mm[2][$i]), (int)str_replace(',','',$mm[3][$i]), (int)str_replace(',','',$mm[4][$i])];
                    $ids[] = $mid;
                }
            }
        }
    }
    $ids = array_unique($ids);
    $out=[];
    foreach($ids as $id){
        $norm = normalize_id($id);
        $p = $pricing[$norm] ?? $pricing[$id] ?? [0,0,0];
        $out[]=[
            'source'=>'opencode',
            'model_id'=>$norm,
            'display_name'=>ucwords(str_replace(['-','.'],' ', $norm)),
            'context'=>'1M',
            'req_5h'=>$p[0],
            'req_week'=>$p[1],
            'req_month'=>$p[2],
            'budget'=>60,
            'input_price'=>null,
            'output_price'=>null,
            'cache_read_price'=>null,
            'intelligence'=>null,
            'tps'=>null,
        ];
    }
    return $out;
}
function parse_commandcode(): array {
    $html = http_get('https://commandcode.ai/models');
    $out=[];
    if ($html) {
        // parse table rows
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $html, $rm)) {
            foreach($rm[1] as $row){
                if (strpos($row,'/models/')===false) continue;
                if (!preg_match('/href=\"\/models\/([^\"]+)\"/', $row, $mm)) continue;
                $mid = strtolower($mm[1]);
                // name
                $name = $mid;
                if (preg_match('/<span class=\"truncate[^>]*>([^<]+)<\/span>/', $row, $nm)) $name = trim($nm[1]);
                // tds
                preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $tdm);
                $tds = $tdm[1] ?? [];
                if (count($tds) < 7) continue;
                $strip = function($s){ return trim(preg_replace('/<[^>]+>/',' ', $s)); };
                $context = $strip($tds[1]);
                $intel_raw = $strip($tds[2]);
                $intel = null;
                if ($intel_raw !== '' && stripos($intel_raw,'not yet')===false) {
                    $intel = is_numeric($intel_raw) ? (float)$intel_raw : null;
                }
                $tps_raw = $strip($tds[3]);
                $tps = is_numeric($tps_raw) ? (float)$tps_raw : null;
                $inp_raw = $strip($tds[4]);
                $out_raw = $strip($tds[5]);
                $cache_raw = $strip($tds[6]);
                $parsePrice = function($s){
                    if (stripos($s,'Free')!==false) return 0.0;
                    if (preg_match('/\$([0-9\.]+)/', $s, $pp)) return (float)$pp[1];
                    return null;
                };
                $inp = $parsePrice($inp_raw);
                $outp = $parsePrice($out_raw);
                $cache = $parsePrice($cache_raw);
                // if all null, skip?
                $out_item = [
                    'source'=>'commandcode',
                    'model_id'=>$mid,
                    'display_name'=>$name,
                    'context'=>$context ?: '1M',
                    'intelligence'=>$intel,
                    'tps'=>$tps,
                    'input_price'=>$inp,
                    'output_price'=>$outp,
                    'cache_read_price'=>$cache,
                    'is_free'=>($inp===0.0 && $outp===0.0)?1:0,
                ];
                // calc req based on GOAT $70 pool but capped per-model allowance logic:
                // For now use $70 for all, but for free use large
                $budget = goat_budget($mid);
                $out_item['budget']=$budget;
                if ($inp===null && $outp===null) {
                    $out_item['req_month']=0; $out_item['req_5h']=0; $out_item['req_week']=0;
                } elseif ($inp===0.0 && $outp===0.0) {
                    $out_item['req_month']=999999; $out_item['req_5h']=199999; $out_item['req_week']=499999;
                } else {
                    $cost = ($inp*800 + $outp*200 + ($cache??0)*50000)/1000000;
                    if ($cost <= 0) $cost = 0.0001;
                    $req_month = (int)($budget / $cost);
                    $out_item['req_month']=$req_month;
                    $out_item['req_5h']=(int)($req_month*14/70);
                    $out_item['req_week']=(int)($req_month*35/70);
                }
                $out[]=$out_item;
            }
        }
    }
    // fallback to seed if parse failed
    if (empty($out)) {
        $seed = [
            ['id'=>'gemini-3-8-flash','name'=>'Gemini 3.8 Flash','int'=>47.1,'tps'=>356,'in'=>1.5,'out'=>7.5,'cache'=>0.15,'ctx'=>'1M'],
            ['id'=>'muse-spark-1-3','name'=>'Muse Spark 1.3','int'=>53.0,'tps'=>221,'in'=>1.25,'out'=>4.25,'cache'=>0.15,'ctx'=>'1M'],
            ['id'=>'muse-spark-1-3-contributor','name'=>'Muse Spark 1.3 Contributor','int'=>53.0,'tps'=>221,'in'=>0.10,'out'=>0.20,'cache'=>0.002,'ctx'=>'1M'],
            ['id'=>'glm-5-3-flash','name'=>'GLM-5.3 Flash','int'=>46.2,'tps'=>59,'in'=>0.15,'out'=>0.50,'cache'=>0.03,'ctx'=>'1M'],
            ['id'=>'longcat-2-0-free','name'=>'LongCat 2.0','int'=>25.8,'tps'=>49,'in'=>0,'out'=>0,'cache'=>0,'ctx'=>'1M','free'=>1],
            ['id'=>'deepseek-v4-flash-vision-exp','name'=>'DeepSeek V4 Flash Vision (exp)','int'=>40.7,'tps'=>120,'in'=>0.22,'out'=>0.66,'cache'=>0.007,'ctx'=>'1M'],
            ['id'=>'glm-5-3','name'=>'GLM-5.3','int'=>48.6,'tps'=>75,'in'=>1.40,'out'=>4.40,'cache'=>0.26,'ctx'=>'1M'],
            ['id'=>'deepseek-v4-pro','name'=>'DeepSeek V4 Pro (latest)','int'=>42.1,'tps'=>62,'in'=>0.66,'out'=>1.98,'cache'=>0.022,'ctx'=>'1M'],
            ['id'=>'grok-4-6','name'=>'Grok 4.6','int'=>50.6,'tps'=>57,'in'=>2.0,'out'=>6.0,'cache'=>0.50,'ctx'=>'500K'],
        ];
        foreach($seed as $s){
            $cost = ($s['in']*800 + $s['out']*200 + $s['cache']*50000)/1000000;
            $req_month = $cost>0 ? (int)(70/$cost) : 999999;
            $out[]=[
                'source'=>'commandcode','model_id'=>strtolower($s['id']),'display_name'=>$s['name'],'context'=>$s['ctx'],'intelligence'=>$s['int'],'tps'=>$s['tps'],'input_price'=>$s['in'],'output_price'=>$s['out'],'cache_read_price'=>$s['cache'],'req_5h'=>(int)($req_month*14/70),'req_week'=>(int)($req_month*35/70),'req_month'=>$req_month,'is_free'=>!empty($s['free'])?1:0
            ];
        }
    }
    return $out;
}
function do_refresh(string $actor='cron', string $type='auto'): array {
    $cc = parse_commandcode();
    $op = parse_opencode();
    // normalize intelligence mapping
    $map=[];
    foreach($cc as $m){ if($m['intelligence']!==null) $map[normalize_id($m['model_id'])] = $m['intelligence']; }
    // also map tps/context
    $ccMap=[];
    foreach($cc as $m){ $ccMap[normalize_id($m['model_id'])]=$m; }
    foreach($op as &$o){
        $norm = normalize_id($o['model_id']);
        if (isset($map[$norm])) $o['intelligence']=$map[$norm];
        // try fuzzy: remove variant suffixes like -free
        if ($o['intelligence']===null) {
            $base = preg_replace('/-free$/','',$norm);
            if (isset($map[$base])) $o['intelligence']=$map[$base];
        }
        if (isset($ccMap[$norm])) { $o['tps']=$ccMap[$norm]['tps']; $o['context']=$ccMap[$norm]['context']; }
        else {
            $base = preg_replace('/-free$/','',$norm);
            if (isset($ccMap[$base])) { $o['tps']=$ccMap[$base]['tps']; $o['context']=$ccMap[$base]['context']; }
        }
    }
    unset($o);
    $now = time();
    // check if DB empty -> baseline: don't mark as new
    $wasEmpty = ((int)db()->query("SELECT COUNT(*) FROM models")->fetchColumn() === 0);
    $baselineTime = $wasEmpty ? $now - 10*86400 : $now;
    $all = array_merge($op,$cc);
    $inserted=0; $updated=0;
    foreach($all as $m){
        $mid = normalize_id($m['model_id']);
        $src = $m['source'];
        $stmt = db()->prepare("SELECT id, first_seen_at FROM models WHERE source=? AND model_id=?");
        $stmt->execute([$src,$mid]);
        $ex = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ex) {
            $first = (int)$ex['first_seen_at'];
            $is_new_flag = ($now - $first) < 7*86400 ? 1 : 0;
            $upd = db()->prepare("UPDATE models SET display_name=?, context=?, intelligence=?, tps=?, input_price=?, output_price=?, cache_read_price=?, req_5h=?, req_week=?, req_month=?, budget=?, updated_at=?, is_new=?, is_free=?, raw_json=? WHERE id=?");
            $upd->execute([$m['display_name'], $m['context']??'1M', $m['intelligence'], $m['tps'],$m['input_price'],$m['output_price'],$m['cache_read_price'],$m['req_5h']??0,$m['req_week']??0,$m['req_month']??0,$m['budget']??($m['source']==='opencode'?60:20),$now,$is_new_flag,$m['is_free']??0,json_encode($m,JSON_UNESCAPED_UNICODE),$ex['id']]);
            $updated++;
        } else {
            $firstSeen = $wasEmpty ? $baselineTime : $now;
            $is_new_flag = $wasEmpty ? 0 : 1;
            $ins = db()->prepare("INSERT INTO models(source,model_id,display_name,context,intelligence,tps,input_price,output_price,cache_read_price,req_5h,req_week,req_month,budget,first_seen_at,updated_at,is_new,is_free,raw_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([$src,$mid,$m['display_name'],$m['context']??'1M',$m['intelligence'],$m['tps'],$m['input_price'],$m['output_price'],$m['cache_read_price'],$m['req_5h']??0,$m['req_week']??0,$m['req_month']??0,$m['budget']??($m['source']==='opencode'?60:20),$firstSeen,$now,$is_new_flag,$m['is_free']??0,json_encode($m,JSON_UNESCAPED_UNICODE)]);
            $inserted++;
        }
    }
    if ($type==='auto') set_meta('last_auto_refresh', (string)$now);
    db()->exec("UPDATE models SET is_new=0 WHERE first_seen_at < ".($now-7*86400));
    db()->exec("UPDATE models SET is_new=1 WHERE first_seen_at >= ".($now-7*86400));
    return ['inserted'=>$inserted,'updated'=>$updated,'total'=>count($all),'opencode'=>count($op),'commandcode'=>count($cc)];
}
