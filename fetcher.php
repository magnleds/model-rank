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
// Official Go pricing from https://opencode.ai/docs/go (Usage limits table)
// Monthly budget per model ($15/$30/$60). DeepSeek = Off-Peak, Qwen Plus/Grok/Luna = <=tier.
function opencode_pricing(): array {
    $raw = [
        'grok-4-6'=>['in'=>2.00,'out'=>6.00,'cache'=>0.50,'cachew'=>null,'budget'=>15],
        'gpt-5-6-luna'=>['in'=>0.20,'out'=>1.20,'cache'=>0.02,'cachew'=>0.25,'budget'=>15],
        'glm-5-3-flash'=>['in'=>0.15,'out'=>0.50,'cache'=>0.03,'cachew'=>null,'budget'=>60],
        'glm-5-3'=>['in'=>1.40,'out'=>4.40,'cache'=>0.26,'cachew'=>null,'budget'=>15],
        'glm-5-2'=>['in'=>1.40,'out'=>4.40,'cache'=>0.26,'cachew'=>null,'budget'=>60],
        'glm-5-1'=>['in'=>1.40,'out'=>4.40,'cache'=>0.26,'cachew'=>null,'budget'=>60],
        'kimi-k3'=>['in'=>3.00,'out'=>15.00,'cache'=>0.30,'cachew'=>null,'budget'=>15],
        'kimi-k2-7-code'=>['in'=>0.95,'out'=>4.00,'cache'=>0.19,'cachew'=>null,'budget'=>60],
        'kimi-k2-6'=>['in'=>0.95,'out'=>4.00,'cache'=>0.16,'cachew'=>null,'budget'=>60],
        'longcat-2-0'=>['in'=>0.30,'out'=>1.20,'cache'=>0.006,'cachew'=>null,'budget'=>60],
        'mimo-v2-5'=>['in'=>0.14,'out'=>0.28,'cache'=>0.0028,'cachew'=>null,'budget'=>60],
        'mimo-v2-5-pro'=>['in'=>0.435,'out'=>0.87,'cache'=>0.003625,'cachew'=>null,'budget'=>15],
        'minimax-m3'=>['in'=>0.30,'out'=>1.20,'cache'=>0.06,'cachew'=>null,'budget'=>60],
        'minimax-m2-7'=>['in'=>0.30,'out'=>1.20,'cache'=>0.06,'cachew'=>0.375,'budget'=>60],
        'minimax-m2-5'=>['in'=>0.30,'out'=>1.20,'cache'=>0.06,'cachew'=>0.375,'budget'=>60],
        'muse-spark-1-3-contributor'=>['in'=>0.10,'out'=>0.20,'cache'=>0.002,'cachew'=>null,'budget'=>60],
        'muse-spark-1-2-contributor'=>['in'=>0.10,'out'=>0.20,'cache'=>0.002,'cachew'=>null,'budget'=>60],
        'qwen3-8-max'=>['in'=>2.00,'out'=>6.00,'cache'=>0.25,'cachew'=>2.50,'budget'=>15],
        'qwen3-8-flash'=>['in'=>0.15,'out'=>0.47,'cache'=>0.016,'cachew'=>0.20,'budget'=>30],
        'qwen3-7-max'=>['in'=>2.50,'out'=>7.50,'cache'=>0.50,'cachew'=>3.125,'budget'=>30],
        'qwen3-7-plus'=>['in'=>0.40,'out'=>1.60,'cache'=>0.04,'cachew'=>0.50,'budget'=>60],
        'qwen3-6-plus'=>['in'=>0.50,'out'=>3.00,'cache'=>0.05,'cachew'=>0.625,'budget'=>60],
        'deepseek-v4-pro'=>['in'=>0.66,'out'=>1.98,'cache'=>0.022,'cachew'=>null,'budget'=>15],
        'deepseek-v4-flash'=>['in'=>0.15,'out'=>0.60,'cache'=>0.003,'cachew'=>null,'budget'=>30],
        'deepseek-flash'=>['in'=>0.15,'out'=>0.60,'cache'=>0.003,'cachew'=>null,'budget'=>15], // live短ID,即文档DeepSeek V4.1 Flash($15)
        'deepseek-v4-flash-vision-exp'=>['in'=>0.15,'out'=>0.60,'cache'=>0.003,'cachew'=>null,'budget'=>15],
        'hy4-preview'=>['in'=>0.834,'out'=>2.501,'cache'=>0.042,'cachew'=>null,'budget'=>30],
        'hy3'=>['in'=>0.14,'out'=>0.58,'cache'=>0.035,'cachew'=>null,'budget'=>60],
        // legacy:不在官方文档但live API仍返回,保留以免变待定价0
        'omen-alpha'=>['in'=>0.20,'out'=>0.66,'cache'=>0.04,'cachew'=>null,'budget'=>100],
    ];
    // normalize keys to dash form
    $out=[];
    foreach($raw as $k=>$v){ $out[normalize_id($k)]=$v; }
    return $out;
}
// Official Estimated requests from https://opencode.ai/docs/go#estimated-requests
// [req_5h, req_week, req_month].以此为准,不再用固定800/200/50k反推(各模型真实用量不同,反推偏差可达3倍).
function opencode_estimated(): array {
    $raw = [
        'glm-5-3-flash'=>[6320,15790,31580],
        'glm-5-3'=>[220,540,1080],
        'glm-5-2'=>[880,2150,4300],
        'glm-5-1'=>[880,2150,4300],
        'kimi-k3'=>[110,250,490],
        'kimi-k2-7-code'=>[1350,3380,6750],
        'kimi-k2-6'=>[1150,2880,5750],
        'longcat-2-0'=>[11400,28600,57200],
        'mimo-v2-5'=>[30100,75200,150400],
        'mimo-v2-5-pro'=>[3250,8150,16300],
        'minimax-m3'=>[3200,8000,16000],
        'minimax-m2-7'=>[3400,8500,17000],
        'muse-spark-1-3-contributor'=>[45300,113300,226600],
        'muse-spark-1-2-contributor'=>[45300,113300,226600],
        'qwen3-8-max'=>[160,400,810],
        'qwen3-8-flash'=>[5400,13500,27000],
        'qwen3-7-max'=>[170,420,840],
        'qwen3-7-plus'=>[4300,10800,21600],
        'qwen3-6-plus'=>[3300,8200,16300],
        'deepseek-v4-1-flash'=>[6500,16250,32500],
        'deepseek-v4-pro'=>[1050,2600,5200],
        'deepseek-v4-flash'=>[13000,32500,65000],
        'deepseek-v4-flash-vision-exp'=>[6500,16250,32500],
        'hy4-preview'=>[1350,3380,6770],
        'hy3'=>[4300,10750,21500],
        'grok-4-6'=>[169,423,845],
        'gpt-5-6-luna'=>[2050,5100,10250],
        // 别名:live API里的deepseek-flash(无版本号)按V4.1 Flash计
        'deepseek-flash'=>[6500,16250,32500],
    ];
    $out=[];
    foreach($raw as $k=>$v){ $out[normalize_id($k)]=$v; }
    return $out;
}
// GOAT Monthly credits from commandcode.ai/docs/plans/goat Usage limits table
function goat_budget(string $mid): int {
    static $map = null;
    if ($map===null){
        $raw = [
            'gpt-5-6-sol'=>70,'glm-5-2'=>70,'tencent-hy3'=>70,'qwen-3-8-27b'=>70,'qwen3-8-27b'=>70,
            'deepseek-v4-flash'=>60,'kimi-k2-7-code'=>60,'kimi-k2.7-code'=>60,
            'minimax-m3'=>47,
            'glm-5-3-flash'=>40,'gemini-3-8-flash'=>40,'gemini-3-7-flash'=>40,
            'qwen-3-7-max'=>33,'qwen3-7-max'=>33,'qwen-3-7-plus'=>33,'qwen3-7-plus'=>33,'qwen-3-6-plus'=>33,'qwen3-6-plus'=>33,
            'qwen-3-8-flash'=>20,'qwen3-8-flash'=>20,'qwen-3-8-max'=>20,'qwen3-8-max'=>20,
            'mimo-v2-5'=>30,
        ];
        $map=[];
        foreach($raw as $k=>$v){ $map[normalize_id($k)]=$v; }
    }
    $norm = normalize_id($mid);
    if (isset($map[$norm])) return $map[$norm];
    $parts = explode('/', $norm);
    $base = end($parts);
    if (isset($map[$base])) return $map[$base];
    return 20;
}
function parse_opencode(): array {
    $pricing = opencode_pricing();
    $json = http_get('https://opencode.ai/zen/go/v1/models');
    $ids = [];
    if ($json) {
        $data = json_decode($json, true);
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $m) if (!empty($m['id'])) $ids[] = normalize_id($m['id']);
        }
    }
    // Canonical: pricing table (docs/go) + any new ids from /v1/models (fallback,待定价)
    $liveIds = $ids;
    $ids = array_keys($pricing);
    // append unknown live ids as fallback entries (new models,待定价)
    foreach($liveIds as $lid){
        if (!isset($pricing[$lid]) && !in_array($lid, $ids)) $ids[] = $lid;
    }
    $est = opencode_estimated();
    $out=[];
    foreach(array_unique($ids) as $id){
        $norm = normalize_id($id);
        $pr = $pricing[$norm] ?? null;
        if (!$pr) {
            // new model not in pricing table yet: show as 待定价, is_new will mark
            $out[]=[
                'source'=>'opencode',
                'model_id'=>$norm,
                'display_name'=>ucwords(str_replace(['-','.'],' ', $norm)),
                'context'=>'1M',
                'req_5h'=>0,'req_week'=>0,'req_month'=>0,'budget'=>60,
                'input_price'=>null,'output_price'=>null,'cache_read_price'=>null,'cache_write_price'=>null,
                'intelligence'=>null,'tps'=>null,
            ];
            continue;
        }
        // 额度优先用官方Estimated requests;无官方数的才按预算÷成本估算
        if (isset($est[$norm])) {
            [$req_5h, $req_week, $req_month] = $est[$norm];
        } else {
            $cost = ($pr['in']*800 + $pr['out']*200 + $pr['cache']*50000)/1000000;
            if ($cost<=0) $cost=0.0001;
            $req_month = (int)($pr['budget'] / $cost);
            $req_5h = (int)($req_month*12/60);
            $req_week = (int)($req_month*30/60);
        }
        $out[]=[
            'source'=>'opencode',
            'model_id'=>$norm,
            'display_name'=>ucwords(str_replace(['-','.'],' ', $norm)),
            'context'=>'1M',
            'req_5h'=>$req_5h,
            'req_week'=>$req_week,
            'req_month'=>$req_month,
            'budget'=>$pr['budget'],
            'input_price'=>$pr['in'],
            'output_price'=>$pr['out'],
            'cache_read_price'=>$pr['cache'],
            'cache_write_price'=>$pr['cachew'],
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
            ['id'=>'deepseek-v4-flash-vision-exp','name'=>'DeepSeek V4 Flash Vision (exp)','int'=>40.7,'tps'=>120,'in'=>0.15,'out'=>0.60,'cache'=>0.003,'ctx'=>'1M'],
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
        // try fuzzy: remove variant suffixes like -free, or add -free (GOAT free variant holds the score)
        if ($o['intelligence']===null) {
            $base = preg_replace('/-free$/','',$norm);
            if (isset($map[$base])) $o['intelligence']=$map[$base];
            elseif (isset($map[$norm.'-free'])) $o['intelligence']=$map[$norm.'-free'];
        }
        if (isset($ccMap[$norm])) { $o['tps']=$ccMap[$norm]['tps']; $o['context']=$ccMap[$norm]['context']; }
        else {
            $base = preg_replace('/-free$/','',$norm);
            if (isset($ccMap[$base])) { $o['tps']=$ccMap[$base]['tps']; $o['context']=$ccMap[$base]['context']; }
            elseif (isset($ccMap[$norm.'-free'])) { $o['tps']=$ccMap[$norm.'-free']['tps']; $o['context']=$ccMap[$norm.'-free']['context']; }
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
            $upd = db()->prepare("UPDATE models SET display_name=?, context=?, intelligence=?, tps=?, input_price=?, output_price=?, cache_read_price=?, cache_write_price=?, req_5h=?, req_week=?, req_month=?, budget=?, updated_at=?, is_new=?, is_free=?, raw_json=? WHERE id=?");
            $upd->execute([$m['display_name'], $m['context']??'1M', $m['intelligence'], $m['tps'],$m['input_price'],$m['output_price'],$m['cache_read_price'],$m['cache_write_price']??null,$m['req_5h']??0,$m['req_week']??0,$m['req_month']??0,$m['budget']??($m['source']==='opencode'?60:20),$now,$is_new_flag,$m['is_free']??0,json_encode($m,JSON_UNESCAPED_UNICODE),$ex['id']]);
            $updated++;
        } else {
            $firstSeen = $wasEmpty ? $baselineTime : $now;
            $is_new_flag = $wasEmpty ? 0 : 1;
            $ins = db()->prepare("INSERT INTO models(source,model_id,display_name,context,intelligence,tps,input_price,output_price,cache_read_price,cache_write_price,req_5h,req_week,req_month,budget,first_seen_at,updated_at,is_new,is_free,raw_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([$src,$mid,$m['display_name'],$m['context']??'1M',$m['intelligence'],$m['tps'],$m['input_price'],$m['output_price'],$m['cache_read_price'],$m['cache_write_price']??null,$m['req_5h']??0,$m['req_week']??0,$m['req_month']??0,$m['budget']??($m['source']==='opencode'?60:20),$firstSeen,$now,$is_new_flag,$m['is_free']??0,json_encode($m,JSON_UNESCAPED_UNICODE)]);
            $inserted++;
        }
    }
    if ($type==='auto') set_meta('last_auto_refresh', (string)$now);
    db()->exec("UPDATE models SET is_new=0 WHERE first_seen_at < ".($now-7*86400));
    db()->exec("UPDATE models SET is_new=1 WHERE first_seen_at >= ".($now-7*86400));
    return ['inserted'=>$inserted,'updated'=>$updated,'total'=>count($all),'opencode'=>count($op),'commandcode'=>count($cc)];
}
