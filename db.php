<?php
if (!defined('APP_ENTRY')) { http_response_code(403); exit('Forbidden'); }
$CONFIG = require __DIR__ . '/config.php';

function db(): PDO {
    global $CONFIG;
    static $pdo = null;
    if ($pdo) return $pdo;
    $path = $CONFIG['db_path'];
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL;');
    $pdo->exec('PRAGMA foreign_keys=ON;');
    init_schema($pdo);
    return $pdo;
}
function init_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS models (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source TEXT NOT NULL,
            model_id TEXT NOT NULL,
            display_name TEXT NOT NULL,
            context TEXT,
            intelligence REAL,
            tps REAL,
            input_price REAL,
            output_price REAL,
            cache_read_price REAL,
            cache_write_price REAL,
            req_5h INTEGER,
            req_week INTEGER,
            req_month INTEGER,
            first_seen_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            is_new INTEGER DEFAULT 0,
            leverage TEXT,
            is_free INTEGER DEFAULT 0,
            is_deal INTEGER DEFAULT 0,
            raw_json TEXT,
            UNIQUE(source, model_id)
        );
        CREATE INDEX IF NOT EXISTS idx_models_source ON models(source);
        CREATE INDEX IF NOT EXISTS idx_models_intelligence ON models(intelligence);
        CREATE INDEX IF NOT EXISTS idx_models_reqmonth ON models(req_month);
        CREATE TABLE IF NOT EXISTS refresh_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT,
            type TEXT,
            created_at INTEGER NOT NULL,
            success INTEGER DEFAULT 1,
            msg TEXT
        );
        CREATE TABLE IF NOT EXISTS meta (
            k TEXT PRIMARY KEY,
            v TEXT
        );
        CREATE TABLE IF NOT EXISTS auth_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token TEXT UNIQUE NOT NULL,
            username TEXT NOT NULL,
            expires_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        );
    ");
    // seed meta defaults
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO meta(k,v) VALUES(?,?)");
    $stmt->execute(['last_auto_refresh', '0']);
    $stmt->execute(['last_manual_refresh', '0']);
}
function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function ok($data = null): void { json_out(['ok'=>true,'data'=>$data]); }
function fail(string $msg, int $code=400): void { json_out(['ok'=>false,'error'=>$msg], $code); }
function input(string $key, $default=null){
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    return $_REQUEST[$key] ?? $body[$key] ?? $default;
}
function get_meta(string $k, $def=''){ $r=db()->prepare("SELECT v FROM meta WHERE k=?"); $r->execute([$k]); $v=$r->fetchColumn(); return $v===false?$def:$v; }
function set_meta(string $k, string $v){ db()->prepare("INSERT INTO meta(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v")->execute([$k,$v]); }
