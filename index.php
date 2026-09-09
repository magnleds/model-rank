<?php
define('APP_ENTRY', true);
require __DIR__ . '/db.php';
db(); // init
// auto seed if empty
$total = (int)db()->query("SELECT COUNT(*) FROM models")->fetchColumn();
if ($total === 0) {
    require __DIR__ . '/fetcher.php';
    require __DIR__ . '/api.php'; // do_refresh defined there? we need to include without action
}
// If still empty, trigger refresh via function
if ($total === 0) {
    // inline refresh
    if (!function_exists('do_refresh')) { require __DIR__ . '/api.php'; }
}
$CONFIG = require __DIR__ . '/config.php';
$last = get_meta('last_auto_refresh','0');
$lastStr = $last!=='0' ? date('Y-m-d H:i', (int)$last) : '未刷新';
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ModelRank · GOAT vs Go 性价比榜</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','system-ui','sans-serif'],mono:['JetBrains Mono','monospace']},colors:{ink:'#0F172A',muted:'#64748B',line:'#E2E8F0',accent:'#F59E0B',accent2:'#EA580C'}}}}</script>
<style>
*{-webkit-font-smoothing:antialiased}
.glass{backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
.score-bar{height:6px;border-radius:9999px;background:#E2E8F0;overflow:hidden}
.score-fill{height:100%;border-radius:9999px;transition:width .6s ease}
.pill{border:1px solid #E2E8F0;padding:6px 12px;border-radius:9999px;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s}
.pill.active{background:#0F172A;color:#fff;border-color:#0F172A}
.tag{font-size:11px;font-weight:600;padding:2px 7px;border-radius:9999px;letter-spacing:.02em}
.sortable{cursor:pointer;user-select:none}
.sortable:hover{color:#0F172A}
.card{border:1px solid #E2E8F0;background:#fff;border-radius:16px}
@media(max-width:768px){.hide-mobile{display:none}}
</style>
</head>
<body class="bg-[#F8FAFC] text-ink font-sans">
<!-- Top Nav -->
<header class="sticky top-0 z-30 bg-white/80 glass border-b border-line">
  <div class="max-w-[1280px] mx-auto px-4 md:px-6 h-[64px] flex items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-400 to-orange-600 flex items-center justify-center text-white font-bold text-[16px]">◈</div>
      <div>
        <div class="font-bold leading-none text-[16px] flex items-center gap-2">ModelRank <span class="tag bg-amber-100 text-amber-700 border border-amber-200">BETA</span></div>
        <div class="text-[12px] text-muted -mt-[2px]">GOAT vs Go · 便宜 × 高分 × 高额度 一眼找出</div>
      </div>
    </div>
    <div class="flex items-center gap-2 md:gap-3">
      <div class="hidden md:flex flex-col items-end leading-tight">
        <span class="text-[12px] text-muted">最后更新</span>
        <span id="lastRefresh" class="text-[13px] font-medium font-mono"><?= htmlspecialchars($lastStr) ?></span>
      </div>
      <button id="btnRefresh" class="inline-flex items-center gap-2 bg-ink text-white px-4 py-2 rounded-full text-[13px] font-semibold hover:bg-black transition">
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> 刷新
      </button>
      <span id="refreshHint" class="hidden md:inline text-[12px] text-muted"></span>
    </div>
  </div>
</header>

<main class="max-w-[1280px] mx-auto px-4 md:px-6 py-6 md:py-8">
  <!-- Hero -->
  <div class="grid grid-cols-12 gap-4 md:gap-6 mb-6">
    <div class="col-span-12 lg:col-span-8 card p-5 md:p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="text-[22px] md:text-[26px] font-bold tracking-tight leading-tight">用<span class="bg-gradient-to-r from-amber-500 to-orange-600 bg-clip-text text-transparent">性价比分</span>挑模型，<br class="hidden md:block">不只看价格，也不只看榜单。</h1>
          <p class="text-[13px] text-muted mt-2 leading-relaxed">默认排序 = <b class="text-ink">Intelligence × 月额度 ÷ 预算</b>（单次成本=800 in+200 out+50k cache，缓存占比最高）。<b>月额度</b>=预算÷单次成本，额度越高越便宜，分数越高越强，<span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span>NEW</span> 为7天内新上架。</p>
        </div>
        <div class="flex gap-2 text-[11px]">
          <span class="tag bg-slate-900 text-white">7x GOAT $10→$70</span>
          <span class="tag bg-white border border-line">6x Go $10→$60</span>
        </div>
      </div>
      <div id="stats" class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-6">
        <!-- filled by JS -->
      </div>
    </div>
    <div class="col-span-12 lg:col-span-4 card p-5">
      <div class="text-[13px] font-semibold flex items-center justify-between">如何算“值” <span class="text-[11px] font-normal text-muted">Score / Cost</span></div>
      <div class="mt-3 space-y-3 text-[13px] leading-relaxed">
        <div class="flex gap-2"><span class="w-6 h-6 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-[12px]">1</span><span><b>先看性价比</b>：高分且便宜的在顶部</span></div>
        <div class="flex gap-2"><span class="w-6 h-6 rounded-lg bg-slate-100 flex items-center justify-center text-[12px]">2</span><span><b>再看额度</b>：月可请求数 = 真实可用量</span></div>
        <div class="flex gap-2"><span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-[12px]">3</span><span><b>新模型高亮</b>：7天内带 <span class="tag bg-amber-400 text-white">NEW</span> 脉冲</span></div>
      </div>
      <div class="mt-4 p-3 rounded-xl bg-amber-50 border border-amber-200 text-[12px] leading-relaxed">
        <b class="text-amber-800">你的目标：</b>找 <span class="font-semibold">价格便宜 / 分数高 / 额度高</span> 的大模型 → 直接用默认排序，前3名就是答案。
      </div>
      <div class="mt-3 flex items-center gap-2 text-[12px] text-muted"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> 每天自动刷新一次，手动每天最多5次</div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card p-3 md:p-4 flex flex-col gap-3">
    <div class="flex flex-col md:flex-row gap-3 md:items-center justify-between">
      <div class="flex items-center gap-2 flex-wrap">
        <button data-source="" class="pill">全部</button>
        <button data-source="commandcode" class="pill">⚡ Command Code GOAT <span id="c-cc" class="ml-1 text-muted">—</span></button>
        <button data-source="opencode" class="pill active">◆ OpenCode Go <span id="c-op" class="ml-1 text-muted">—</span></button>
        <button id="btnNew" class="pill">✦ 只看 NEW <span id="c-new" class="ml-1">—</span></button>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <div class="relative flex-1 md:w-[220px] min-w-[180px]">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted">⌕</span>
          <input id="q" placeholder="搜索模型，如 Muse Spark / DeepSeek" class="w-full pl-8 pr-3 py-2 rounded-full border border-line bg-[#F8FAFC] text-[13px] outline-none focus:border-amber-400 focus:bg-white">
        </div>
        <select id="sort" class="px-3 py-2 rounded-full border border-line bg-white text-[13px] font-medium">
          <option value="value">排序 · 性价比 ★</option>
          <option value="intelligence">排序 · 分数</option>
          <option value="req_month">排序 · 月额度</option>
          <option value="cache">排序 · 缓存便宜</option>
          <option value="input">排序 · 输入价便宜</option>
          <option value="output">排序 · 输出价便宜</option>
          <option value="budget">排序 · 预算小</option>
          <option value="tps">排序 · 速度</option>
        </select>
        <button id="btnDir" class="pill" title="切换升/降序">↓ 降序</button>
        <button id="btnReset" class="pill">↺ 重置</button>
      </div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-[12px]">
      <label class="flex items-center gap-2 bg-[#F8FAFC] border border-line rounded-full px-3 py-2">分数 <select id="fIntel" class="flex-1 bg-transparent outline-none font-medium"><option value="">全部</option><option value="30">≥30</option><option value="40" selected>≥40</option><option value="45">≥45</option><option value="50">≥50</option></select></label>
      <label class="flex items-center gap-2 bg-[#F8FAFC] border border-line rounded-full px-3 py-2">额度 <select id="fReq" class="flex-1 bg-transparent outline-none font-medium"><option value="">全部</option><option value="5000">≥5k/月</option><option value="20000">≥20k/月</option><option value="50000">≥50k/月</option><option value="100000">≥100k/月</option></select></label>
      <label class="flex items-center gap-2 bg-[#F8FAFC] border border-line rounded-full px-3 py-2">缓存≤ <select id="fCache" class="flex-1 bg-transparent outline-none font-medium"><option value="">不限</option><option value="0.01">$0.01</option><option value="0.05">$0.05</option><option value="0.1">$0.1</option><option value="0.5">$0.5</option></select></label>
      <label class="flex items-center gap-2 bg-[#F8FAFC] border border-line rounded-full px-3 py-2">输入≤ <select id="fInput" class="flex-1 bg-transparent outline-none font-medium"><option value="">不限</option><option value="0.5">$0.5</option><option value="1">$1.0</option><option value="2">$2.0</option></select></label>
    </div>
    <div class="flex items-center gap-2 text-[12px] text-muted flex-wrap">
      <span>快捷：</span>
      <button class="tag bg-white border border-line hover:border-ink" data-quick="high">高分 &gt;45</button>
      <button class="tag bg-white border border-line hover:border-ink" data-quick="cheap">月 &gt;20k</button>
      <button class="tag bg-white border border-line hover:border-ink" data-quick="free">Free</button>
      <span id="activeFilters" class="text-amber-700 font-medium"></span>
      <span class="ml-auto hidden md:inline">共 <b id="total" class="text-ink">—</b> 个模型 · 已筛选 <b id="filtered" class="text-ink">—</b></span>
    </div>
  </div>

  <!-- Table -->
  <div class="card mt-4 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-[13px]">
        <thead class="bg-[#F8FAFC] border-b border-line text-[11px] tracking-wide text-muted font-semibold">
          <tr>
            <th class="text-left px-4 py-3 w-[48px]">#</th>
            <th class="text-left px-3 py-3 min-w-[220px]">模型</th>
            <th class="text-left px-3 py-3">平台</th>
            <th class="sortable px-3 py-3 text-left" data-sort="intelligence">Intelligence <span class="text-[10px]">↕</span></th>
            <th class="px-3 py-3 text-left hide-mobile">上下文</th>
            <th class="sortable px-3 py-3 text-left hide-mobile" data-sort="tps">速度 <span class="text-[10px]">↕</span></th>
            <th class="px-3 py-3 text-left min-w-[180px]">单价 / 1M <span class="text-[10px] text-muted">(含缓存)</span></th>
            <th class="sortable px-3 py-3 text-left bg-amber-50" data-sort="req_month">月额度 <span class="text-[10px]">↕</span> <span class="text-[10px] text-amber-700 font-bold">★主要参考</span></th>
            <th class="sortable px-3 py-3 text-left" data-sort="value">性价比 <span class="text-[10px]">↕</span></th>
          </tr>
        </thead>
        <tbody id="tbody" class="divide-y divide-line">
          <tr><td colspan="9" class="px-6 py-12 text-center text-muted">加载中…</td></tr>
        </tbody>
      </table>
    </div>
    <div class="px-4 py-3 bg-[#F8FAFC] border-t border-line flex items-center justify-between text-[12px] text-muted">
      <span>数据来自官方文档与 /v1/models，每日自动更新</span>
      <span class="hidden md:inline">Tip: 点表头可切换排序 · 移动端可横滑</span>
    </div>
  </div>

  <p class="text-center text-[12px] text-muted mt-6">ModelRank · 独立项目 · 无需登录 · 开源比价 · <a href="https://opencode.ai/docs/go" target="_blank" class="underline">OpenCode Go</a> · <a href="https://commandcode.ai/docs/plans/goat" target="_blank" class="underline">GOAT</a></p>
</main>

<script>
const $ = s=>document.querySelector(s);
const tbody = $('#tbody');
let data = [];
let filters = {q:'', source:'opencode', only_new:false, sort:'value', dir:'desc', quick:new Set(), min_intel:'40', min_req:'', max_cache:'', max_input:''};

function fmt(n){ if(n>=1000000) return (n/1000000).toFixed(1)+'M'; if(n>=1000) return (n/1000).toFixed(n>=10000?0:1)+'k'; return String(n); }
function price(v){ if(v===null||v===undefined) return '<span class="text-muted">—</span>'; if(v===0) return '<span class="tag bg-emerald-500 text-white">FREE</span>'; let s=Number(v); if(s<0.01) return '$'+s.toFixed(3); if(s<0.1) return '$'+s.toFixed(3); return '$'+s.toFixed(2); }

async function load(){
  const p = new URLSearchParams({action:'list_models', q:filters.q, source:filters.source, only_new:filters.only_new?1:0, sort:filters.sort, dir:filters.dir});
  if(filters.min_intel) p.set('min_intel', filters.min_intel);
  if(filters.min_req) p.set('min_req', filters.min_req);
  if(filters.max_cache) p.set('max_cache', filters.max_cache);
  if(filters.max_input) p.set('max_input', filters.max_input);
  const r = await fetch('api.php?'+p.toString());
  const j = await r.json();
  if(!j.ok){ tbody.innerHTML='<tr><td colspan=9 class="px-6 py-8 text-center text-red-600">'+j.error+'</td></tr>'; return; }
  data = j.data.rows;
  // client quick filters (multi)
  if(filters.quick.has('high')) data = data.filter(x=> (x.intelligence||0) >= 45);
  if(filters.quick.has('cheap')) data = data.filter(x=> (x.req_month||0) >= 20000);
  if(filters.quick.has('free')) data = data.filter(x=> x.is_free==1 || x.is_deal==1 || (x.input_price==0 && x.output_price==0));
  render(j.data);
}

function render(meta){
  $('#total').textContent = meta.total;
  $('#filtered').textContent = data.length;
  $('#c-cc').textContent = meta.commandcode;
  $('#c-op').textContent = meta.opencode;
  $('#c-new').textContent = meta.new_count;
  const last = meta.last_refresh ? new Date(meta.last_refresh*1000).toLocaleString('zh-CN',{month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'}) : '—';
  $('#lastRefresh').textContent = last;

  // stats cards
  const avg = data.length ? (data.reduce((s,x)=>s+(x.intelligence||0),0)/data.filter(x=>x.intelligence).length||0) : 0;
  const topInt = data.length ? Math.max(...data.map(x=>x.intelligence||0)) : 0;
  const topM = data.find(x=> (x.intelligence||0)===topInt);
  const cheap = data.length ? data.reduce((a,b)=> (a.req_month||0)>(b.req_month||0)?a:b, data[0]) : null;
  $('#stats').innerHTML = `
    <div class="rounded-xl border border-line p-3 bg-white"><div class="text-[11px] text-muted">总模型数</div><div class="text-[22px] font-bold">${meta.total}</div><div class="text-[12px] text-muted">GOAT ${meta.commandcode} · Go ${meta.opencode}</div></div>
    <div class="rounded-xl border border-line p-3 bg-white"><div class="text-[11px] text-muted">平均 Intelligence</div><div class="text-[22px] font-bold">${avg.toFixed(1)}</div><div class="text-[12px] text-muted">有分模型均值</div></div>
    <div class="rounded-xl border border-line p-3 bg-white"><div class="text-[11px] text-muted">最高分</div><div class="text-[18px] font-bold truncate">${topM?topM.display_name:'—'} <span class="text-amber-600">${topInt||'—'}</span></div><div class="text-[12px] text-muted">分数优先首选</div></div>
    <div class="rounded-xl border border-line p-3 bg-gradient-to-br from-amber-500 to-orange-600 text-white"><div class="text-[11px] text-white/80">最多额度</div><div class="text-[18px] font-bold truncate">${cheap?cheap.display_name:'—'}</div><div class="text-[12px] text-white/80">${cheap?fmt(cheap.req_month)+'/月':''} · 最便宜</div></div>
  `;

  if(!data.length){ tbody.innerHTML='<tr><td colspan=9 class="px-6 py-12 text-center text-muted">无匹配结果</td></tr>'; return; }
  tbody.innerHTML = data.map((r,i)=>{
    const isNew = (Date.now()/1000 - r.first_seen_at) < 7*86400;
    const rank = i+1;
    const medal = rank===1?'🥇':rank===2?'🥈':rank===3?'🥉':'';
    const intel = r.intelligence;
    const intelStr = intel===null ? '<span class="text-muted text-[12px]">待评分</span>' : `<span class="font-mono font-semibold">${Number(intel).toFixed(1)}</span>`;
    const pct = intel===null?0:Math.min(100, (intel/56)*100);
    const platform = r.source==='opencode' ? '<span class="tag bg-slate-900 text-white">Go</span>' : '<span class="tag bg-amber-500 text-white">GOAT</span>';
    const cacheClass = r.cache_read_price===null ? 'text-muted' : (r.cache_read_price<=0.02 ? 'text-emerald-600 font-semibold' : (r.cache_read_price>=0.1 ? 'text-red-600' : 'text-amber-600'));
    const pricing = r.source==='opencode'
      ? `<span class="font-mono text-[12px]">${fmt(r.req_month)}/月 <span class="text-[11px] text-muted">预算$${r.budget||60}</span></span><div class="text-[11px] text-muted font-mono">${fmt(r.req_5h)}/5h</div>`
      : `<span class="font-mono text-[12px]">${price(r.input_price)} → ${price(r.output_price)} <span class="${cacheClass}">cache ${price(r.cache_read_price)}</span></span><div class="text-[11px] text-muted">预算 $${r.budget||20} → ${fmt(r.req_month)}/月</div>`;
    const value = Number(r.value_score||0);
    const valueLabel = value>900 ? '极高' : value>400 ? '高' : value>150 ? '中' : '低';
    const valueColor = value>900?'bg-emerald-500':value>400?'bg-amber-500':value>150?'bg-slate-700':'bg-slate-300';
    return `<tr class="hover:bg-amber-50/40 ${isNew?'bg-amber-50/30':''}">
      <td class="px-4 py-3 font-mono text-[12px]">${medal} ${rank}</td>
      <td class="px-3 py-3">
        <div class="flex items-center gap-2">
          <div class="font-semibold leading-tight truncate max-w-[160px] md:max-w-[200px]">${r.display_name}</div>
          ${isNew?'<span class="tag bg-amber-400 text-white animate-pulse">NEW '+r.days_old+'d</span>':''}
          ${r.is_free?'<span class="tag bg-emerald-500 text-white">FREE</span>':''}
        </div>
        <div class="text-[11px] text-muted font-mono truncate">${r.model_id}</div>
      </td>
      <td class="px-3 py-3">${platform}</td>
      <td class="px-3 py-3 min-w-[140px]">
        <div class="flex items-center gap-2">${intelStr}</div>
        <div class="score-bar mt-1 w-[110px]"><div class="score-fill ${intel===null?'bg-slate-200': intel>48?'bg-emerald-500': intel>40?'bg-amber-500':'bg-slate-700'}" style="width:${pct}%"></div></div>
      </td>
      <td class="px-3 py-3 hide-mobile font-mono text-[12px]">${r.context||'1M'}</td>
      <td class="px-3 py-3 hide-mobile font-mono text-[12px]">${r.tps? r.tps+' tok/s' : '<span class="text-muted">—</span>'}</td>
      <td class="px-3 py-3">${pricing}</td>
      <td class="px-3 py-3 bg-amber-50/50 border-l border-amber-200"><div class="font-mono font-bold text-[14px] text-amber-700">${fmt(r.req_month||0)}/月</div><div class="text-[11px] font-mono text-muted">$${r.budget|| (r.source==='opencode'?60:20)}预算 · ${fmt(r.req_5h||0)}/5h</div></td>
      <td class="px-3 py-3"><span class="inline-flex items-center gap-2"><span class="w-2 h-2 rounded-full ${valueColor}"></span><span class="font-semibold">${valueLabel}</span></span><div class="text-[11px] text-muted font-mono">${value.toFixed(0)}</div></td>
    </tr>`;
  }).join('');
}

// events
document.querySelectorAll('[data-source]').forEach(b=>{
  b.addEventListener('click',()=>{
    document.querySelectorAll('[data-source]').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    filters.source = b.dataset.source;
    load();
  });
});
$('#btnNew').addEventListener('click',()=>{
  filters.only_new = !filters.only_new;
  $('#btnNew').classList.toggle('active', filters.only_new);
  load();
});
$('#q').addEventListener('input', e=>{ filters.q=e.target.value; clearTimeout(window._t); window._t=setTimeout(load,300); });
$('#sort').addEventListener('change', e=>{ filters.sort=e.target.value; load(); });
document.querySelectorAll('[data-quick]').forEach(b=>{
  b.addEventListener('click',()=>{
    const v=b.dataset.quick;
    if(filters.quick.has(v)) { filters.quick.delete(v); b.classList.remove('!bg-ink','!text-white'); }
    else { filters.quick.add(v); b.classList.add('!bg-ink','!text-white'); }
    // sync to dropdowns
    if(v==='high'){ filters.min_intel = filters.quick.has('high') ? '45' : ''; $('#fIntel').value = filters.min_intel; }
    if(v==='cheap'){ filters.min_req = filters.quick.has('cheap') ? '20000' : ''; $('#fReq').value = filters.min_req; }
    load();
  });
});
['fIntel','fReq','fCache','fInput'].forEach(id=>{
  document.getElementById(id).addEventListener('change', e=>{
    const v=e.target.value;
    if(id==='fIntel') filters.min_intel=v;
    if(id==='fReq') filters.min_req=v;
    if(id==='fCache') filters.max_cache=v;
    if(id==='fInput') filters.max_input=v;
    updateActive();
    load();
  });
});
$('#btnDir').addEventListener('click', ()=>{
  filters.dir = filters.dir==='desc'?'asc':'desc';
  $('#btnDir').textContent = filters.dir==='desc' ? '↓ 降序' : '↑ 升序';
  load();
});
$('#btnReset').addEventListener('click', ()=>{
  filters = {q:'', source:filters.source, only_new:false, sort:'value', dir:'desc', quick:new Set(), min_intel:'', min_req:'', max_cache:'', max_input:''};
  $('#q').value=''; $('#sort').value='value'; $('#fIntel').value=''; $('#fReq').value=''; $('#fCache').value=''; $('#fInput').value='';
  $('#btnNew').classList.remove('active'); $('#btnDir').textContent='↓ 降序';
  document.querySelectorAll('[data-quick]').forEach(x=>x.classList.remove('!bg-ink','!text-white'));
  updateActive(); load();
});
function updateActive(){
  const parts=[];
  if(filters.min_intel) parts.push('分数≥'+filters.min_intel);
  if(filters.min_req) parts.push('额度≥'+fmt(filters.min_req));
  if(filters.max_cache) parts.push('缓存≤$'+filters.max_cache);
  if(filters.max_input) parts.push('输入≤$'+filters.max_input);
  if(filters.only_new) parts.push('NEW');
  document.getElementById('activeFilters').textContent = parts.length ? '已选: '+parts.join(' · ') : '';
}
document.querySelectorAll('.sortable').forEach(th=>{
  th.addEventListener('click',()=>{
    const s=th.dataset.sort;
    if(filters.sort===s) filters.dir = filters.dir==='desc'?'asc':'desc';
    else { filters.sort=s; filters.dir='desc'; }
    $('#sort').value = filters.sort;
    load();
  });
});
$('#btnRefresh').addEventListener('click', async ()=>{
  const btn=$('#btnRefresh'); btn.disabled=true; btn.textContent='刷新中…';
  try{
    const r=await fetch('api.php?action=refresh',{method:'POST'});
    const j=await r.json();
    if(!j.ok) throw new Error(j.error);
    $('#refreshHint').textContent = `新增 ${j.data.inserted} · 更新 ${j.data.updated}`;
    await load();
  }catch(e){ alert(e.message); }
  finally{ btn.disabled=false; btn.innerHTML='<span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> 刷新'; }
});

// initial load, if empty trigger refresh
load().then(()=>{
  if(data.length===0){
    fetch('api.php?action=refresh',{method:'POST'}).then(()=>load());
  }
});
</script>
</body>
</html>
