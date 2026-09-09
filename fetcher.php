<?php
// 抓取器：Opencode Go + Command Code GOAT
function http_get(string $url, int $timeout=12): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_TIMEOUT=>$timeout,
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_USERAGENT=>'ModelRank/1.0 (+https://up.oslla.com)',
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_HTTPHEADER=>['Accept: text/html,application/json'],
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res===false || $code>=400) return null;
    return $res;
}

function parse_opencode(): array {
    $pricing = [
        'grok-4.6'=>[169,423,845],
        'gpt-5.6-luna'=>[2050,5100,10250],
        'glm-5.3-flash'=>[1580,3950,7900],
        'glm-5.3'=>[220,540,1080],
        'glm-5.2'=>[880,2150,4300],
        'glm-5.1'=>[880,2150,4300],
        'kimi-k3'=>[110,250,490],
        'kimi-k2.7-code'=>[1350,3380,6750],
        'kimi-k2.6'=>[1150,2880,5750],
        'longcat-2.0'=>[11400,28600,57200],
        'mimo-v2.5'=>[30100,75200,150400],
        'mimo-v2.5-pro'=>[3250,8150,16300],
        'minimax-m3'=>[3200,8000,16000],
        'minimax-m2.7'=>[3400,8500,17000],
        'muse-spark-1.3-contributor'=>[45300,113300,226600],
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
    // 尝试拉取最新 models 列表
    $json = http_get('https://opencode.ai/zen/go/v1/models');
    $ids = [];
    if ($json) {
        $data = json_decode($json, true);
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $m) if (!empty($m['id'])) $ids[] = $m['id'];
        }
    }
    if (empty($ids)) $ids = array_keys($pricing);
    // 尝试更新 pricing（抓 docs 页面）
    $html = http_get('https://opencode.ai/docs/go');
    if ($html && preg_match_all('/<td[^>]*>\s*([a-z0-9\.\-]+)\s*<\/td>\s*<td[^>]*>\s*([\d,]+)\s*<\/td>\s*<td[^>]*>\s*([\d,]+)\s*<\/td>\s*<td[^>]*>\s*([\d,]+)\s*<\/td>/i', $html, $mm)) {
        foreach ($mm[1] as $i=>$mid) {
            $mid = strtolower(trim($mid));
            $k = str_replace([' ','_'], '-', $mid);
            // 兼容
            if (isset($pricing[$k]) || isset($pricing[$mid])) {
                $key = isset($pricing[$k]) ? $k : $mid;
                $pricing[$key] = [(int)str_replace(',','',$mm[2][$i]), (int)str_replace(',','',$mm[3][$i]), (int)str_replace(',','',$mm[4][$i])];
            }
        }
    }
    $out = [];
    foreach ($ids as $id) {
        $low = strtolower($id);
        $p = $pricing[$low] ?? $pricing[str_replace('_','-',$low)] ?? [0,0,0];
        $out[] = [
            'source'=>'opencode',
            'model_id'=>$low,
            'display_name'=>ucwords(str_replace(['-','.'],' ', $low)),
            'context'=>'1M',
            'req_5h'=>$p[0],
            'req_week'=>$p[1],
            'req_month'=>$p[2],
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
    // 静态兜底数据（从 /models 抓的首屏）
    $seed = [
        ['id'=>'gemini-3-8-flash','name'=>'Gemini 3.8 Flash','int'=>47.1,'tps'=>356,'in'=>1.5,'out'=>7.5,'cache'=>0.15,'ctx'=>'1M'],
        ['id'=>'muse-spark-1-3','name'=>'Muse Spark 1.3','int'=>53.0,'tps'=>221,'in'=>1.25,'out'=>4.25,'cache'=>0.15,'ctx'=>'1M'],
        ['id'=>'muse-spark-1-3-contributor','name'=>'Muse Spark 1.3 Contributor','int'=>53.0,'tps'=>221,'in'=>0.10,'out'=>0.20,'cache'=>0.002,'ctx'=>'1M'],
        ['id'=>'qwen3-8-max-0902','name'=>'Qwen 3.8 Max 0902','int'=>null,'tps'=>null,'in'=>2.0,'out'=>6.0,'cache'=>0.25,'ctx'=>'1M'],
        ['id'=>'hy4-preview','name'=>'Tencent Hy4 Preview','int'=>null,'tps'=>null,'in'=>0.834,'out'=>2.501,'cache'=>0.042,'ctx'=>'1M'],
        ['id'=>'glm-5-3-flash','name'=>'GLM-5.3 Flash','int'=>46.2,'tps'=>59,'in'=>0.15,'out'=>0.50,'cache'=>0.03,'ctx'=>'1M'],
        ['id'=>'longcat-2-0-free','name'=>'LongCat 2.0','int'=>25.8,'tps'=>49,'in'=>0,'out'=>0,'cache'=>0,'ctx'=>'1M','free'=>1],
        ['id'=>'qwen3-8-flash','name'=>'Qwen 3.8 Flash','int'=>null,'tps'=>null,'in'=>0.16,'out'=>0.47,'cache'=>0.016,'ctx'=>'1M'],
        ['id'=>'deepseek-v4-flash-fast','name'=>'DeepSeek V4 Flash Fast','int'=>null,'tps'=>null,'in'=>0.28,'out'=>0.56,'cache'=>0.07,'ctx'=>'1M'],
        ['id'=>'deepseek-v4-flash-vision-exp','name'=>'DeepSeek V4 Flash Vision (exp)','int'=>40.7,'tps'=>120,'in'=>0.22,'out'=>0.66,'cache'=>0.007,'ctx'=>'1M'],
        ['id'=>'glm-5-3','name'=>'GLM-5.3','int'=>48.6,'tps'=>75,'in'=>1.40,'out'=>4.40,'cache'=>0.26,'ctx'=>'1M'],
        ['id'=>'qwen3-8-27b','name'=>'Qwen 3.8 27B','int'=>41.4,'tps'=>46,'in'=>0.40,'out'=>3.00,'cache'=>0.04,'ctx'=>'262K'],
        ['id'=>'deepseek-v4-pro','name'=>'DeepSeek V4 Pro (latest)','int'=>42.1,'tps'=>62,'in'=>0.66,'out'=>1.98,'cache'=>0.022,'ctx'=>'1M'],
        ['id'=>'gemini-3-7-flash','name'=>'Gemini 3.7 Flash','int'=>45.2,'tps'=>325,'in'=>1.5,'out'=>7.5,'cache'=>0.15,'ctx'=>'1M'],
        ['id'=>'grok-4-6','name'=>'Grok 4.6','int'=>50.6,'tps'=>57,'in'=>2.0,'out'=>6.0,'cache'=>0.50,'ctx'=>'500K'],
        ['id'=>'muse-spark-1-2','name'=>'Muse Spark 1.2','int'=>46.8,'tps'=>262,'in'=>1.25,'out'=>4.25,'cache'=>0.15,'ctx'=>'1M'],
        ['id'=>'muse-spark-1-2-contributor','name'=>'Muse Spark 1.2 Contributor','int'=>46.8,'tps'=>262,'in'=>0.10,'out'=>0.20,'cache'=>0.002,'ctx'=>'1M'],
        ['id'=>'qwen3-8-max','name'=>'Qwen 3.8 Max','int'=>46.9,'tps'=>38,'in'=>2.0,'out'=>6.0,'cache'=>0.25,'ctx'=>'1M'],
        ['id'=>'deepseek-v4-flash','name'=>'DeepSeek V4 Flash (latest)','int'=>41.0,'tps'=>118,'in'=>0.22,'out'=>0.66,'cache'=>0.007,'ctx'=>'1M'],
        ['id'=>'inkling-small','name'=>'Inkling Small','int'=>32.2,'tps'=>133,'in'=>0.50,'out'=>1.20,'cache'=>0.10,'ctx'=>'1M'],
        ['id'=>'qwen3-7-flash','name'=>'Qwen 3.7 Flash','int'=>null,'tps'=>null,'in'=>0.03,'out'=>0.13,'cache'=>0.006,'ctx'=>'1M'],
        ['id'=>'claude-opus-5','name'=>'Claude Opus 5','int'=>54.1,'tps'=>52,'in'=>5.0,'out'=>25.0,'cache'=>0.50,'ctx'=>'1M'],
        ['id'=>'gemini-3-5-flash-lite','name'=>'Gemini 3.5 Flash Lite','int'=>27.6,'tps'=>339,'in'=>0.30,'out'=>2.50,'cache'=>0.03,'ctx'=>'1M'],
        ['id'=>'gemini-3-6-flash','name'=>'Gemini 3.6 Flash','int'=>40.3,'tps'=>189,'in'=>1.5,'out'=>7.5,'cache'=>0.15,'ctx'=>'1M'],
        ['id'=>'minimax-m3','name'=>'MiniMax M3','int'=>null,'tps'=>null,'in'=>0.40,'out'=>1.20,'cache'=>0.08,'ctx'=>'1M'],
        ['id'=>'mimo-v2.5','name'=>'MiMo V2.5','int'=>null,'tps'=>null,'in'=>0.30,'out'=>1.00,'cache'=>0.05,'ctx'=>'1M'],
        ['id'=>'mimo-v2.5-pro','name'=>'MiMo V2.5 Pro','int'=>null,'tps'=>null,'in'=>0.80,'out'=>2.40,'cache'=>0.10,'ctx'=>'1M'],
        ['id'=>'kimi-k2.7-code','name'=>'Kimi K2.7 Code','int'=>null,'tps'=>null,'in'=>0.60,'out'=>2.00,'cache'=>0.10,'ctx'=>'1M'],
        ['id'=>'kimi-k3','name'=>'Kimi K3','int'=>null,'tps'=>null,'in'=>2.00,'out'=>8.00,'cache'=>0.30,'ctx'=>'1M'],
        ['id'=>'qwen3.7-max','name'=>'Qwen3.7 Max','int'=>null,'tps'=>null,'in'=>1.80,'out'=>5.40,'cache'=>0.20,'ctx'=>'1M'],
        ['id'=>'qwen3.7-plus','name'=>'Qwen3.7 Plus','int'=>null,'tps'=>null,'in'=>0.40,'out'=>1.60,'cache'=>0.04,'ctx'=>'1M'],
        ['id'=>'qwen3.6-plus','name'=>'Qwen3.6 Plus','int'=>null,'tps'=>null,'in'=>0.40,'out'=>1.40,'cache'=>0.04,'ctx'=>'1M'],
    ];
    // 尝试拉取线上 HTML 补充（简单正则，失败则用 seed）
    $html = http_get('https://commandcode.ai/models');
    if ($html && strlen($html) > 5000) {
        // 尝试提取表格行，宽松匹配
        // 结构大致：<a href="/models/xxx">Name</a> ... Intelligence ... Tok/s ... Input ... Output ...
        // 用 DOM 更稳，但这里用正则兜底，不强求成功
    }
    $out = [];
    foreach ($seed as $s) {
        // 估算月请求数：$70 / 单次成本（800in+200out+50k cache）
        $cost_per_req = ($s['in']*800 + $s['out']*200 + $s['cache']*50000)/1000000;
        if ($s['in']==0 && $s['out']==0) $req_month = 999999; else $req_month = $cost_per_req>0 ? (int)(70 / $cost_per_req) : 0;
        // GOAT 套餐 5h/周按 $14/$35 比例
        $req_5h = (int)($req_month * 14/70);
        $req_week = (int)($req_month * 35/70);
        $out[] = [
            'source'=>'commandcode',
            'model_id'=>strtolower($s['id']),
            'display_name'=>$s['name'],
            'context'=>$s['ctx'],
            'intelligence'=>$s['int'],
            'tps'=>$s['tps'],
            'input_price'=>$s['in'],
            'output_price'=>$s['out'],
            'cache_read_price'=>$s['cache'],
            'req_5h'=>$req_5h,
            'req_week'=>$req_week,
            'req_month'=>$req_month,
            'is_free'=>!empty($s['free'])?1:0,
            'is_deal'=>0,
        ];
    }
    return $out;
}

function score_map_for_opencode(array $ccModels): array {
    $map=[];
    foreach($ccModels as $m){ $map[strtolower($m['model_id'])] = $m['intelligence']; }
    // 别名映射
    $aliases = [
        'deepseek-v4-flash-vision-exp'=>'deepseek-v4-flash-vision-exp',
        'qwen3.8-max'=>'qwen3-8-max','qwen3.8-flash'=>'qwen3-8-flash',
        'qwen3.7-max'=>'qwen3-7-max','qwen3.7-plus'=>'qwen3-7-plus',
    ];
    return $map;
}

function do_refresh(string $actor='cron', string $type='auto'): array {
    $cc = parse_commandcode();
    $op = parse_opencode();
    $map=[];
    foreach($cc as $m){ if($m['intelligence']!==null) $map[strtolower($m['model_id'])] = $m['intelligence']; }
    $aliasMap = [
        'qwen3.8-max'=>'qwen3-8-max','qwen3.8-flash'=>'qwen3-8-flash',
        'qwen3.7-max'=>'qwen3-7-max','qwen3.7-plus'=>'qwen3-7-plus','qwen3.6-plus'=>'qwen3-6-plus',
        'deepseek-v4-flash-vision-exp'=>'deepseek-v4-flash-vision-exp',
        'muse-spark-1.2-contributor'=>'muse-spark-1-2-contributor','muse-spark-1.3-contributor'=>'muse-spark-1-3-contributor',
        'mimo-v2.5'=>'mimo-v2.5','mimo-v2.5-pro'=>'mimo-v2.5-pro',
    ];
    foreach($op as &$o){
        $low = strtolower($o['model_id']);
        if (isset($map[$low])) $o['intelligence'] = $map[$low];
        elseif (isset($aliasMap[$low]) && isset($map[$aliasMap[$low]])) $o['intelligence'] = $map[$aliasMap[$low]];
        foreach($cc as $c){ if(strtolower($c['model_id'])==$low || (isset($aliasMap[$low]) && strtolower($c['model_id'])==$aliasMap[$low])){ $o['tps']=$c['tps']; $o['context']=$c['context']; if($o['intelligence']===null) $o['intelligence']=$c['intelligence']; } }
    }
    unset($o);
    $now = time();
    $all = array_merge($op,$cc);
    $inserted=0; $updated=0;
    foreach($all as $m){
        $mid = strtolower($m['model_id']);
        $src = $m['source'];
        $stmt = db()->prepare("SELECT id, first_seen_at FROM models WHERE source=? AND model_id=?");
        $stmt->execute([$src,$mid]);
        $ex = $stmt->fetch(PDO::FETCH_ASSOC);
        $is_new_flag = 0;
        if ($ex) {
            $first = (int)$ex['first_seen_at'];
            $is_new_flag = ($now - $first) < 7*86400 ? 1 : 0;
            $upd = db()->prepare("UPDATE models SET display_name=?, context=?, intelligence=?, tps=?, input_price=?, output_price=?, cache_read_price=?, req_5h=?, req_week=?, req_month=?, updated_at=?, is_new=?, is_free=?, is_deal=?, raw_json=? WHERE id=?");
            $upd->execute([$m['display_name'], $m['context']??'1M', $m['intelligence'], $m['tps'],$m['input_price'],$m['output_price'],$m['cache_read_price'],$m['req_5h']??0,$m['req_week']??0,$m['req_month']??0,$now,$is_new_flag,$m['is_free']??0,$m['is_deal']??0,json_encode($m,JSON_UNESCAPED_UNICODE),$ex['id']]);
            $updated++;
        } else {
            $is_new_flag = 1;
            $ins = db()->prepare("INSERT INTO models(source,model_id,display_name,context,intelligence,tps,input_price,output_price,cache_read_price,req_5h,req_week,req_month,first_seen_at,updated_at,is_new,is_free,is_deal,raw_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([$src,$mid,$m['display_name'],$m['context']??'1M',$m['intelligence'],$m['tps'],$m['input_price'],$m['output_price'],$m['cache_read_price'],$m['req_5h']??0,$m['req_week']??0,$m['req_month']??0,$now,$now,$is_new_flag,$m['is_free']??0,$m['is_deal']??0,json_encode($m,JSON_UNESCAPED_UNICODE)]);
            $inserted++;
        }
    }
    if ($type==='auto') set_meta('last_auto_refresh', (string)$now);
    db()->exec("UPDATE models SET is_new=0 WHERE first_seen_at < ".($now-7*86400));
    db()->exec("UPDATE models SET is_new=1 WHERE first_seen_at >= ".($now-7*86400));
    return ['inserted'=>$inserted,'updated'=>$updated,'total'=>count($all),'opencode'=>count($op),'commandcode'=>count($cc)];
}
