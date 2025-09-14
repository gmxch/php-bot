<?php
// zero.php (versi loop terus menerus dengan while(true))
// Semua fungsi tetap sama, hanya ditambahkan while(true) agar claim jalan nonstop.

// ANSI colors
const C_RESET = "\033[0m";
const C_RED   = "\033[1;31m";
const C_GREEN = "\033[1;32m";
const C_YELLOW= "\033[1;33m";
const C_BLUE  = "\033[1;34m";
const C_MAG   = "\033[1;35m";

function logLine($level, $color, $msg) {
    $time = date('Y-m-d H:i:s');
    $pad = str_pad("[$level]", 7);
    echo "{$color}{$pad}" . C_RESET . " {$time} - {$msg}\n";
    //file_put_contents(__DIR__ . '/logs/all.log', "{$time} [{$level}] {$msg}\n", FILE_APPEND);
}
function info($m) { logLine('INFO', C_BLUE, $m); }
function ok($m)   { logLine('OK',   C_GREEN,$m); }
function err($m)  { logLine('ERR',  C_RED,  $m); }
function urlLog($m){ logLine('URL',  C_MAG,  $m); }

// Config
$cookiesDir   = __DIR__ . '/cookies';

//$uagent = file('USRAGNT.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); 
$uagent = file(__DIR__ . '/../USRAGNT.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); 
$userAgent = $uagent[array_rand($uagent)];
echo "set UA => $userAgent\n";
$ua = $userAgent;

if (!is_dir($cookiesDir)) mkdir($cookiesDir, 0777, true);

$wallet = getenv('LOGIN');
//$wallet = readline('wallet: ');
$wallets = [$wallet];  // bikin array satu elemen


function curlRequestFull($url, $cookieFile, $ua, $timeout=30) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_COOKIEJAR  => $cookieFile,
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['ok' => ($body !== false), 'body' => $body, 'http' => $http, 'err' => $err];
}

// Resolve relative URLs (handles //host, /root, relative paths)
function resolveUrl($base, $rel) {
    // if already absolute
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $rel)) return $rel;
    if (strpos($rel, '//') === 0) {
        $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
        return $scheme . ':' . $rel;
    }
    $baseParts = parse_url($base);
    $scheme = $baseParts['scheme'] ?? 'https';
    $host = $baseParts['host'] ?? '';
    $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
    if (strpos($rel, '/') === 0) {
        return "$scheme://$host$port$rel";
    }
    // remove query from base path
    $path = $baseParts['path'] ?? '/';
    $dir = preg_replace('#/[^/]*$#', '/', $path);
    $full = $dir . $rel;
    // normalize ../ and ./
    $segments = explode('/', $full);
    $resolved = [];
    foreach ($segments as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..') { array_pop($resolved); continue; }
        $resolved[] = $seg;
    }
    return "$scheme://$host$port/" . implode('/', $resolved);
}

// Utility: try several patterns to extract reward.php link
function findRewardLink($html) {
    $patterns = [
        '/href=["\']([^"\']*reward\.php[^"\']*)["\']/i',
        '/href=["\']([^"\']*reward\.php[^"\']*)/i',
        '/(reward\.php\?[^"\'\s<>]*)/i',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $html, $m)) return html_entity_decode($m[1]);
    }
    // iframe src
    if (preg_match_all('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $html, $iframes)) {
        foreach ($iframes[1] as $src) {
            if (stripos($src, 'reward.php') !== false) return html_entity_decode($src);
        }
    }
    return false;
}

function findConfirmLink($html) {
    $patterns = [
        '/href=["\']([^"\']*index\.php\?confirm1=[^"\']*)["\']/i',
        '/(index\.php\?confirm1=[^"\'\s<>]*)/i',
    ];
    foreach ($patterns as $p) if (preg_match($p, $html, $m)) return html_entity_decode($m[1]);
    return false;
}

function findJsLocation($html) {
    $patterns = [
        '/window\.location\.href\s*=\s*["\']([^"\']+)["\']/i',
        '/location\.href\s*=\s*["\']([^"\']+)["\']/i',
        '/window\.location\s*=\s*["\']([^"\']+)["\']/i',
    ];
    foreach ($patterns as $p) if (preg_match($p, $html, $m)) return html_entity_decode($m[1]);
    return false;
}

function findMetaRefresh($html) {
    if (preg_match('/<meta[^>]*http-equiv=["\']?refresh["\']?[^>]*content=["\']?\s*\d+\s*;\s*url=([^"\'>]+)["\']?/i', $html, $m)) return html_entity_decode($m[1]);
    return false;
}

// Extract amount like + 11,835 &nbsp; Zatoshi  OR + 11835 Zatoshi etc.
function extractZatoshiAmount($html) {
    if (preg_match('/\+\s*([0-9][0-9\.,]*)\s*(?:&nbsp;|\s)*Zatoshi/i', $html, $m)) {
        $raw = $m[1];
        // remove non-digit
        $num = preg_replace('/[^0-9]/', '', $raw);
        return $num;
    }
    // sometimes shown as only digits before the word
    if (preg_match('/([0-9][0-9\.,]*)\s*(?:&nbsp;|\s)*Zatoshi/i', $html, $m)) {
        $num = preg_replace('/[^0-9]/', '', $m[1]);
        return $num;
    }
    return false;
}

// ==========================================
// MAIN LOOP — jalan terus menerus
// ==========================================
while (true) {
    foreach ($wallets as $wallet) {
        $wallet = trim($wallet);
        if ($wallet === '') continue;

        info("=== Mulai wallet: $wallet ===");
        $cookieFile = $cookiesDir . '/' . hash('sha256', $wallet) . '.cookie';

        // login
        $loginUrl = 'https://zerocoin.top/index.php?bitcoinwallet=' . urlencode($wallet) . '&ref=';
        curlRequestFull($loginUrl, $cookieFile, $ua);

        info('Menunggu 2 detik...');
        sleep(5);

        // === CLAIM ===
        $claimUrl = 'https://zerocoin.top/index.php?claim=1';
        $claimResp = curlRequestFull($claimUrl, $cookieFile, $ua);
        if (!$claimResp['ok']) { err("Gagal request claim: {$claimResp['err']}"); continue; }
        $claimBody = $claimResp['body'];

        // cari redirect
        $redirect = findJsLocation($claimBody) ?: findMetaRefresh($claimBody);
        if (!$redirect && preg_match('/href=["\']([^"\']+)["\']/', $claimBody, $ma)) $redirect = $ma[1];
        if (!$redirect) { err('Tidak menemukan redirect URL'); continue; }

        $rewardRedirectUrl = (strpos($redirect, 'http') === 0) ? $redirect : resolveUrl($claimUrl, $redirect);
        urlLog('Redirect reward URL: ' . $rewardRedirectUrl);

        $rewardResp = curlRequestFull($rewardRedirectUrl, $cookieFile, $ua);
        if (!$rewardResp['ok']) { err('Gagal fetch reward redirect'); continue; }
        $rewardHtml = $rewardResp['body'];

        $rewardLink = findRewardLink($rewardHtml);
        if (!$rewardLink) {
            // cek js redirect di reward page
            $jsLoc = findJsLocation($rewardHtml);
            if ($jsLoc) {
                $landerUrl = (strpos($jsLoc,'http')===0)?$jsLoc:resolveUrl($rewardRedirectUrl,$jsLoc);
                urlLog("JS Redirect: $landerUrl");
                $landerResp = curlRequestFull($landerUrl, $cookieFile, $ua);
                if ($landerResp['ok']) $rewardLink = findRewardLink($landerResp['body']);
                if ($rewardLink && strpos($rewardLink,'http')!==0) $rewardLink = resolveUrl($landerUrl,$rewardLink);
            }
        } else {
            if (strpos($rewardLink,'http')!==0) $rewardLink = resolveUrl($rewardRedirectUrl,$rewardLink);
        }
        if (!$rewardLink) { err('Tidak menemukan reward.php link'); continue; }

        urlLog('Final Reward URL: ' . $rewardLink);

        sleep(2);
        $finalResp = curlRequestFull($rewardLink, $cookieFile, $ua);
        if (!$finalResp['ok']) { err('Gagal fetch final reward'); continue; }
        $finalHtml = $finalResp['body'];

        $confirmLink = findConfirmLink($finalHtml);
        if ($confirmLink && strpos($confirmLink,'http')!==0) $confirmLink = resolveUrl($rewardLink,$confirmLink);
        if (!$confirmLink) { err('Tidak menemukan confirm link'); continue; }

        urlLog('Confirm URL: ' . $confirmLink);

        sleep(5);
        $confirmResp = curlRequestFull($confirmLink, $cookieFile, $ua);
        if (!$confirmResp['ok']) { err('Gagal request confirm'); continue; }

        $confirmHtml = $confirmResp['body'];
        $amount = extractZatoshiAmount($confirmHtml);
        if ($amount !== false) {
            ok('Confirm berhasil (HTTP '.$confirmResp['http'].')');
            ok('Saldo bertambah: '.$amount.' Zatoshi');
            //file_put_contents(...);
        } else {
            if (stripos($confirmHtml,'Zatoshi')!==false) {
                ok('Confirm berhasil tapi jumlah tidak terdeteksi.');
            } else {
                err('Confirm tidak valid.');
            }
        }

        info("=== Selesai wallet: $wallet ===");
        sleep(10); // jeda kecil antar wallet
    }

    // Tidak ada sleep di sini → langsung ulang
    info("Proses selesai untuk semua wallet. Ulangi lagi...");
}
