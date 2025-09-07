<?php
date_default_timezone_set("Asia/Kuala_Lumpur");

// 🎨 Header
echo "\n\033[1;35m🔥 BOT COOLFAUCET - Created by Akiefx 🇲🇾\033[0m\n";

$cookie = getenv('COOKIE');
$uagent = file('USRAGNT.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$userAgent = $uagent[array_rand($uagent)];
echo "set UA => $userAgent\n";

// 🔗 URL aksi
$attackUrl     = "https://coolfaucet.hu/dashboard";
$statusUrl     = "https://coolfaucet.hu/dashboard";
$rewardUrl     = "https://coolfaucet.hu/claim-reward";
$claimCardUrl  = "https://coolfaucet.hu/dashboard/claim_card";
$withdrawUrl   = "https://coolfaucet.hu/withdraw";
$referer       = "https://coolfaucet.hu/";

// ❤️ Semak HP monster
function getMonsterHP($url, $cookie, $userAgent, $referer) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIE => $cookie,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => ["Referer: $referer"]
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    preg_match('/HP:\s*(\d+)\s*\/\s*\d+/', $html, $hpMatch);
    return intval($hpMatch[1]?? 0);
}

// 💰 Ambil balance dari dashboard
function getBalance($url, $cookie, $userAgent, $referer) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIE => $cookie,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => ["Referer: $referer"]
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    preg_match('/Your current balance:\s*([\d\.]+)/i', $html, $match);
    return floatval($match[1]?? 0);
}

// ⚔️ Serang monster
function attackMonster($url, $cookie, $userAgent, $referer) {
    $data = ['attack' => 1];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_COOKIE => $cookie,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => [
            "Referer: $referer",
            "Content-Type: application/x-www-form-urlencoded"
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    echo "[". date("H:i:s"). "] \033[1;32m⚔️ Serangan dihantar.\033[0m\n";
    echo "[". date("H:i:s"). "] 📩 Respons: ". trim(strip_tags($response)). "\n";
}

// 🎁 Klaim reward
function claimReward($url, $cookie, $userAgent, $referer) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_COOKIE => $cookie,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => ["Referer: $referer"]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    echo "[". date("H:i:s"). "] \033[1;33m🎁 Reward dituntut.\033[0m\n";
    echo "[". date("H:i:s"). "] 📩 Respons: ". trim(strip_tags($response)). "\n";
}

// 🃏 Klaim kartu
function claimInventoryCard($statusUrl, $claimCardUrl, $cookie, $userAgent, $referer) {
    $ch = curl_init($statusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIE => $cookie,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => ["Referer: $referer"]
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    if (strpos($html, 'You have won an Inventory Card')!== false) {
        echo "[". date("H:i:s"). "] \033[1;35m🃏 Kartu ditemukan! Mengklaim...\033[0m\n";
        $ch = curl_init($claimCardUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_COOKIE => $cookie,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => ["Referer: $referer"]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        echo "[". date("H:i:s"). "] 🎉 Kartu diklaim.\n";
        echo "[". date("H:i:s"). "] 📩 Respons: ". trim(strip_tags($response)). "\n";
}
}

// 💸 Auto withdraw
function autoWithdraw($withdrawUrl, $cookie, $userAgent, $referer) {
    $data = [
        'withdraw_all' => '1',
        'currency' => 'LTC'
    ];
    $ch = curl_init($withdrawUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_COOKIE => $cookie,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => [
            "Referer: $referer",
            "Content-Type: application/x-www-form-urlencoded"
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    echo "[". date("H:i:s"). "] \033[1;36m💸 Auto WD ke FaucetPay dihantar.\033[0m\n";
    echo "[". date("H:i:s"). "] 📩 Respons: ". trim(strip_tags($response)). "\n";
}

// 🔁 Loop utama
while (true) {
    echo "\n[". date("H:i:s"). "] \033[1;35m🔄 Kitaran bermula...\033[0m\n";

    $hp = getMonsterHP($statusUrl, $cookie, $userAgent, $referer);
    echo "[". date("H:i:s"). "] ❤️ HP Monster: \033[1;36m$hp\033[0m\n";

    if ($hp> 0) {
        attackMonster($attackUrl, $cookie, $userAgent, $referer);
} else {
        claimReward($rewardUrl, $cookie, $userAgent, $referer);
}

    claimInventoryCard($statusUrl, $claimCardUrl, $cookie, $userAgent, $referer);

    $balance = getBalance($statusUrl, $cookie, $userAgent, $referer);
    echo "[". date("H:i:s"). "] 💰 Balance: \033[1;32m$balance LTC\033[0m\n";

    if ($balance>= 0.00005) {
        autoWithdraw($withdrawUrl, $cookie, $userAgent, $referer);
}

    sleep(60); // ⏄1�7 Tunggu 1 minit sebelum ulang
}