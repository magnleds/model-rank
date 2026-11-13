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
// Official Go pricing from https://opencode.ai/docs/go (Usage limits table, Go plan)
// Synced 2026-11-06. Tiers now $15/$20/$30/$60/$100. DeepSeek V4.1 Flash = Off-Peak $100.
// Qwen Plus/Grok/Luna/Claude/Gemini = <=tier. Kimi K3 has Cached Write $3.00.
function opencode_pricing(): array {
    $raw = [
        'grok-4-7'=>['in'=>2.00,'out'=>6.00,'cache'=>0.50,'cachew'=>null,'budget'=>15],
        'gpt-6-luna'=>['in'=>0.10,'out'=>0.50,'cache'=>0.01,'cachew'=>0.125,'budget'=>15],
        'gpt-5-6-luna'=>['in'=>0.20,'out'=>1.20,'cache'=>0.02,'cachew'=>0.25,'budget'=>15],
        'glm-5-3-flash'=>['in'=>0.15,'out'=>0.50,'cache'=>0.03,'cachew'=>null,'budget'=>60],
        'glm-5-3'=>['in'=>1.40,'out'=>4.40,'cache'=>0.26,'cachew'=>null,'budget'=>15],
        'glm-5-2'=>['in'=>1.40,'out'=>4.40,'cache'=>0.26,'cachew'=>null,'budget'=>60],
        'kimi-k3-1-pro'=>['in'=>2.00,'out'=>10.00,'cache'=>0.20,'cachew'=>2.00,'budget'=>15],
        'kimi-k3'=>['in'=>3.00,'out'=>15.00,'cache'=>0.30,'cachew'=>3.00,'budget'=>15],
        'kimi-k2-7-code'=>['in'=>0.95,'out'=>4.00,'cache'=>0.19,'cachew'=>null,'budget'=>60],
        'longcat-2-0'=>['in'=>0.30,'out'=>1.20,'cache'=>0.006,'cachew'=>null,'budget'=>60],
        'step-5-preview'=>['in'=>1.00,'out'=>2.70,'cache'=>0.10,'cachew'=>null,'budget'=>15],
        'mimo-v2-5'=>['in'=>0.14,'out'=>0.28,'cache'=>0.0028,'cachew'=>null,'budget'=>60],
        'mimo-v2-5-pro'=>['in'=>0.435,'out'=>0.87,'cache'=>0.003625,'cachew'=>null,'budget'=>15],
        'mimo-v2-6-flash'=>['in'=>0.14,'out'=>0.28,'cache'=>0.0028,'cachew'=>null,'budget'=>60],
        'mimo-v2-6-pro'=>['in'=>0.435,'out'=>0.87,'cache'=>0.003625,'cachew'=>null,'budget'=>15],
        'minimax-m3-1-flash'=>['in'=>0.15,'out'=>0.60,'cache'=>0.015,'cachew'=>null,'budget'=>20],
        'minimax-m3'=>['in'=>0.30,'out'=>1.20,'cache'=>0.06,'cachew'=>null,'budget'=>60],
        'muse-spark-1-3-contributor'=>['in'=>0.10,'out'=>0.20,'cache'=>0.002,'cachew'=>null,'budget'=>60],
        'muse-spark-1-2-contributor'=>['in'=>0.10,'out'=>0.20,'cache'=>0.002,'cachew'=>null,'budget'=>60],
        'qwen3-8-max'=>['in'=>2.00,'out'=>6.00,'cache'=>0.25,'cachew'=>2.50,'budget'=>15],
        'qwen3-8-flash'=>['in'=>0.15,'out'=>0.47,'cache'=>0.016,'cachew'=>0.20,'budget'=>30],
        'qwen3-7-plus'=>['in'=>0.40,'out'=>1.60,'cache'=>0.04,'cachew'=>0.50,'budget'=>60],
        'deepseek-v4-pro'=>['in'=>0.66,'out'=>1.98,'cache'=>0.022,'cachew'=>null,'budget'=>15],
        'deepseek-v4-1-flash'=>['in'=>0.15,'out'=>0.60,'cache'=>0.003,'cachew'=>null,'budget'=>100], // Off-Peak,月限$100
        'hy4'=>['in'=>0.834,'out'=>2.501,'cache'=>0.042,'cachew'=>null,'budget'=>60],
        'hy4-preview'=>['in'=>0.834,'out'=>2.501,'cache'=>0.042,'cachew'=>null,'budget'=>30],
        'hy3'=>['in'=>0.14,'out'=>0.58,'cache'=>0.035,'cachew'=>null,'budget'=>60],
        'claude-haiku-5-5'=>['in'=>0.10,'out'=>0.50,'cache'=>0.01,'cachew'=>0.125,'budget'=>15], // <=100K tier
        'gemini-4-argon'=>['in'=>2.00,'out'=>10.00,'cache'=>0.10,'cachew'=>null,'budget'=>30], // 50% off后价
    ];
    // normalize keys to dash form
    $out=[];
    foreach($raw as $k=>$v){ $out[normalize_id($k)]=$v; }
    return $out;
}
// Official Estimated requests from https://opencode.ai/docs/go#estimated-requests (Go plan)
// Synced 2026-11-06. [req_5h, req_week, req_month].以此为准,不再用固定800/200/50k反推(各模型真实用量不同,反推偏差可达3倍).
function opencode_estimated(): array {
    $raw = [
        'glm-5-3-flash'=>[6320,15790,31580],
        'glm-5-3'=>[220,540,1080],
        'glm-5-2'=>[880,2150,4300],
        'kimi-k3-1-pro'=>[147,368,735],
        'kimi-k3'=>[110,250,490],
        'kimi-k2-7-code'=>[1350,3380,6750],
        'longcat-2-0'=>[11400,28600,57200],
        'step-5-preview'=>[430,1070,2130],
        'mimo-v2-5'=>[30100,75200,150400],
        'mimo-v2-5-pro'=>[3250,8150,16300],
        'mimo-v2-6-flash'=>[30100,75200,150400],
        'mimo-v2-6-pro'=>[3250,8150,16300],
        'minimax-m3-1-flash'=>[3880,9690,19380],
        'minimax-m3'=>[3200,8000,16000],
        'muse-spark-1-3-contributor'=>[45300,113300,226600],
        'muse-spark-1-2-contributor'=>[45300,113300,226600],
        'qwen3-8-max'=>[160,400,810],
        'qwen3-8-flash'=>[5400,13500,27000],
        'qwen3-7-plus'=>[4300,10800,21600],
        'deepseek-v4-1-flash'=>[43330,108330,216670],
        'deepseek-v4-pro'=>[1050,2600,5200],
        'hy4'=>[2710,6770,13500],
        'hy4-preview'=>[1350,3380,6770],
        'hy3'=>[4300,10750,21500],
        'grok-4-7'=>[169,423,845],
        'gpt-6-luna'=>[4230,10560,21130],
        'gpt-5-6-luna'=>[2050,5100,10250],
        'claude-haiku-5-5'=>[3850,9620,19230],
        'gemini-4-argon'=>[440,1080,2120],
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
            'deepseek-v4-flash'=>60,'deepseek-v4-1-flash'=>60,'kimi-k2-7-code'=>60,'kimi-k2.7-code'=>60,
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
// Official Estimated requests from https://commandcode.ai/docs/plans/goat#usage-limits
// [req_5h, req_week, req_month].以此为准,不再用固定800/200/50k反推(各系列单次output不同+解析价易错,MiMo曾差97倍).
function goat_estimated(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $raw = [
        'gpt-5-6-sol'=>[414,1040,2070],
        'deepseek-v4-flash'=>[30800,76900,154000],
        'deepseek-v4-1-flash'=>[30800,76900,154000],
        'glm-5-2'=>[947,2370,4740],
        'gpt-5-6-luna'=>[2960,7400,14800],
        'tencent-hy3'=>[7080,17700,35400],
        'qwen3-8-27b'=>[4790,12000,24000],
        'qwen3-8-max'=>[261,654,1310],
        'qwen3-8-max-0902'=>[261,654,1310],
        'qwen3-7-max'=>[232,579,1160],
        'qwen3-7-plus'=>[1420,3560,7110],
        'qwen3-6-plus'=>[1100,2750,5500],
        'minimax-m3'=>[2770,6930,13900],
        'kimi-k2-7-code'=>[1080,2710,5420],
        'glm-5-3-flash'=>[4720,11800,23600],
        'gemini-3-8-flash'=>[784,1960,3920],
        'gemini-3-7-flash'=>[784,1960,3920],
        'mimo-v2-5'=>[19500,48700,97400],
        'deepseek-v4-pro'=>[1980,4940,9880],
        'mimo-v2-5-pro'=>[5700,14200,28500],
        'hy4-preview'=>[1220,3060,6120],
        'qwen3-8-flash'=>[3910,9780,19600],
        'deepseek-v4-flash-vision-exp'=>[6080,15200,30400],
        'deepseek-v4-flash-fast'=>[1040,2610,5210],
        'glm-5-3'=>[271,677,1350],
        'muse-spark-1-3'=>[428,1070,2140],
        'muse-spark-1-3-contributor'=>[18200,45500,90900],
        'muse-spark-1-2'=>[428,1070,2140],
        'muse-spark-1-2-contributor'=>[18200,45500,90900],
        'kimi-k3'=>[196,490,980],
        'kimi-k2-7-code-highspeed'=>[181,452,904],
        'grok-4-5'=>[144,360,719],
        'grok-4-6'=>[144,360,719],
        'glm-5-2-fast'=>[138,346,691],
        'inkling'=>[396,989,1980],
        'inkling-small'=>[709,1770,3550],
        'step-3-7-flash'=>[1670,4180,8370],
        'step-3-5-flash'=>[3510,8770,17500],
        'nemotron-3-ultra'=>[575,1440,2870],
        // 别名:live slug带参数后缀
        'nemotron-3-ultra-550b-a55b'=>[575,1440,2870],
    ];
    $out=[];
    foreach($raw as $k=>$v){ $out[normalize_id($k)]=$v; }
    $cache = $out;
    return $out;
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
    // 官网限时免费不限量(Unlimited):Beam Free / Iceman Contributor Free
    $freeNames = ['beam-free'=>'Beam Free','iceman-contributor-free'=>'Iceman Contributor Free'];
    $out=[];
    foreach(array_unique($ids) as $id){
        $norm = normalize_id($id);
        $pr = $pricing[$norm] ?? null;
        if (!$pr) {
            if (isset($freeNames[$norm])) {
                // 官网限时免费不限量:按FREE不限量收录
                $out[]=[
                    'source'=>'opencode',
                    'model_id'=>$norm,
                    'display_name'=>$freeNames[$norm],
                    'context'=>'1M',
                    'req_5h'=>199999,'req_week'=>499999,'req_month'=>999999,'budget'=>0,
                    'input_price'=>0.0,'output_price'=>0.0,'cache_read_price'=>0.0,'cache_write_price'=>null,
                    'intelligence'=>null,'tps'=>null,'is_free'=>1,
                ];
                continue;
            }
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
                // 官网表格2026-10起为8列:模型/上下文/分数/Input/Output/Cache read/Cache write/Caps(无TPS列);兼容旧9列布局
                if (count($tds) >= 9) {
                    $context = $strip($tds[1]);
                    $intel_raw = $strip($tds[2]);
                    $tps_raw = $strip($tds[3]);
                    $tps = is_numeric($tps_raw) ? (float)$tps_raw : null;
                    $inp_raw = $tds[4]; $out_raw = $tds[5]; $cache_raw = $tds[6]; $cachew_raw = $tds[7];
                } else {
                    $context = $strip($tds[1]);
                    $intel_raw = $strip($tds[2]);
                    $tps = null;
                    $inp_raw = $tds[3]; $out_raw = $tds[4]; $cache_raw = $tds[5]; $cachew_raw = $tds[6];
                }
                $intel = null;
                if ($intel_raw !== '' && stripos($intel_raw,'not yet')===false) {
                    $intel = is_numeric($intel_raw) ? (float)$intel_raw : null;
                }
                $parsePrice = function($s){
                    $s = preg_replace('/<s\b.*?<\/s>/s', '', $s); // 去掉划线老价(含属性),留deal现价
                    $s = preg_replace('/<button\b.*?<\/button>/s', '', $s); // 去掉+1提示按钮(含aria峰时价),留表格展示价(非峰价)
                    if (stripos($s,'Free')!==false) return 0.0;
                    if (preg_match('/\$([0-9\.]+)/', $s, $pp)) return (float)$pp[1];
                    return null;
                };
                $inp = $parsePrice($inp_raw);
                $outp = $parsePrice($out_raw);
                $cache = $parsePrice($cache_raw);
                $cachew = $parsePrice($cachew_raw);
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
                    'cache_write_price'=>$cachew,
                    'is_free'=>($inp===0.0 && $outp===0.0)?1:0,
                ];
                $budget = goat_budget($mid);
                $out_item['budget']=$budget;
                if ($inp===null && $outp===null) {
                    $out_item['req_month']=0; $out_item['req_5h']=0; $out_item['req_week']=0;
                } elseif ($inp===0.0 && $outp===0.0) {
                    $out_item['req_month']=999999; $out_item['req_5h']=199999; $out_item['req_week']=499999;
                } else {
                    // 额度优先用官方估算表;无官方数的才按credits÷成本估算
                    $gest = goat_estimated();
                    $gnorm = normalize_id($mid);
                    if (isset($gest[$gnorm])) {
                        [$h5, $wk, $mo] = $gest[$gnorm];
                        $out_item['req_5h']=$h5; $out_item['req_week']=$wk; $out_item['req_month']=$mo;
                    } else {
                        $cost = ($inp*800 + $outp*200 + ($cache??0)*50000)/1000000;
                        if ($cost <= 0) $cost = 0.0001;
                        $req_month = (int)($budget / $cost);
                        $out_item['req_month']=$req_month;
                        $out_item['req_5h']=(int)($req_month*14/70);
                        $out_item['req_week']=(int)($req_month*35/70);
                    }
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
    // 覆盖分：以 commandcode 详情页抓到的 Intelligence index 为准（scores.php 产出，cron 02:50 刷新）
    if (is_file(__DIR__ . '/data/scores.json')) {
        $sd = json_decode((string)file_get_contents(__DIR__ . '/data/scores.json'), true);
        $scores = $sd['scores'] ?? [];
        $lookup = function(string $id) use ($scores): ?float {
            $n = normalize_id($id);
            $cands = [$n, $n . '-free', (string)preg_replace('/-free$/','',$n)];
            // 变体回退：contributor / preview / exp 没单独评分时继承主版本
            foreach (['-contributor','-preview','-exp'] as $suf) {
                if (substr($n, -strlen($suf)) === $suf) $cands[] = substr($n, 0, -strlen($suf));
            }
            foreach ($cands as $k) {
                if (isset($scores[$k]) && $scores[$k]['intel'] !== null) return (float)$scores[$k]['intel'];
            }
            return null;
        };
        foreach ($all as &$mm) {
            // 一律以 scores.json 为准：列表页 <tr> 解析出的"分数"其实是价格等错位字段（0.1/0.16 之类）
            $mm['intelligence'] = $lookup((string)$mm['model_id']);
        }
        unset($mm);
    }
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
    // 清理:opencode源连续7天既不在文档定价表也不在live列表的模型(已下架/轮换结束),避免榜单堆积死数据
    $canon = [];
    foreach($all as $m){ if($m['source']==='opencode') $canon[] = normalize_id($m['model_id']); }
    $canon = array_values(array_unique($canon));
    if (!empty($canon)) {
        $place = implode(',', array_fill(0, count($canon), '?'));
        $del = db()->prepare("DELETE FROM models WHERE source='opencode' AND updated_at < ? AND model_id NOT IN ($place)");
        $del->execute(array_merge([$now-7*86400], $canon));
        $pruned = $del->rowCount();
    } else { $pruned = 0; }
    return ['inserted'=>$inserted,'updated'=>$updated,'total'=>count($all),'opencode'=>count($op),'commandcode'=>count($cc),'pruned'=>$pruned];
}
