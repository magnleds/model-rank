<?php
/**
 * 抓 commandcode 各模型详情页的 Intelligence / Coding index。
 * 背景：列表页 https://commandcode.ai/models 是 React 渲染的空表，fetcher 的 <tr> 解析只能拿到 3 行，
 * 导致 113 个 commandcode 模型 + 44 个 opencode 模型里只有 12 个有分。
 * 详情页 https://commandcode.ai/models/<slug> 是服务端渲染，分数直接写在 HTML 里。
 * 本脚本产出 data/scores.json，供 fetcher.php 合并。
 * 用法：php scores.php
 */
define('APP_ENTRY', true);
require_once __DIR__ . '/db.php';

const BASE = 'https://commandcode.ai';
const PARALLEL = 10;

function sc_get(string $url, int $timeout = 20): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ModelRankBot/1.0)',
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200 && is_string($res)) ? $res : null;
}

function sc_num(string $html, string $label): ?float {
    // 分数固定落在 label 之后：<div class="mt-1 text-[21px] font-semibold tabular-nums">39.5</div>
    // 未评分时该 div 里是 <span>not yet scored</span>
    if (!preg_match('/' . preg_quote($label, '/') . '.{0,900}?tabular-nums">(.*?)</s', $html, $m)) return null;
    $v = trim(strip_tags($m[1]));
    if (!is_numeric($v)) return null;
    $f = (float)$v;
    return ($f > 5 && $f < 120) ? $f : null;
}

$list = sc_get(BASE . '/models');
if (!$list) { fwrite(STDERR, "list page failed\n"); exit(1); }
preg_match_all('#' . preg_quote(BASE, '#') . '/models/([a-z0-9\-\.]+)"#', $list, $mm);
$slugs = array_values(array_unique($mm[1]));
if (!$slugs) { fwrite(STDERR, "no slugs parsed\n"); exit(1); }
echo 'slugs: ' . count($slugs) . "\n";

$scores = [];
$mh = curl_multi_init();
$handles = [];
$queue = $slugs;
$done = 0;

$add = function (string $slug) use ($mh, &$handles) {
    $ch = curl_init(BASE . '/models/' . $slug);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ModelRankBot/1.0)',
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[(int)$ch] = $slug;
};

while ($queue && count($handles) < PARALLEL) $add(array_shift($queue));

do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh, 1.0);
    while ($info = curl_multi_info_read($mh)) {
        $ch = $info['handle'];
        $slug = $handles[(int)$ch] ?? '';
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $body = curl_multi_getcontent($ch);
        $intel = null; $coding = null;
        if ($code === 200 && is_string($body)) {
            $intel = sc_num($body, 'Intelligence index');
            $coding = sc_num($body, 'Coding index');
        }
        $scores[$slug] = ['intel' => $intel, 'coding' => $coding];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        unset($handles[(int)$ch]);
        $done++;
        if ($queue) $add(array_shift($queue));
    }
} while ($handles);

curl_multi_close($mh);

$scored = count(array_filter($scores, fn($v) => $v['intel'] !== null));
$out = ['fetched_at' => time(), 'total' => count($scores), 'scored' => $scored, 'scores' => $scores];
@mkdir(__DIR__ . '/data', 0775, true);
file_put_contents(__DIR__ . '/data/scores.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "done: {$done} pages, scored {$scored}/" . count($scores) . "\n";
