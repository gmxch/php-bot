<?php
date_default_timezone_set("Asia/Kuala_Lumpur");

// ðŸŽ¨ Header
echo "\n\033[1;35mðŸ”¥ BOT COOLFAUCET - Created by Akiefx ðŸ‡²ðŸ‡¾\033[0m\n";

$cookie = getenv('COOKIE');
$uagent = file(__DIR__ . '/../USRAGNT.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$userAgent = $uagent[array_rand($uagent)];
echo "set UA => $userAgent\n";

// ðŸ”— URL aksi
$attackUrl     = "https://coolfaucet.hu/dashboard";
$statusUrl     = "https://coolfaucet.hu/dashboard";
$rewardUrl     = "https://coolfaucet.hu/claim-reward";
$claimCardUrl  = "https://coolfaucet.hu/dashboard/claim_card";
$withdrawUrl   = "https://coolfaucet.hu/withdraw";
$referer       = "https://coolfaucet.hu/";

// â¤ï¸ Semak HP monster
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

// ðŸ’° Ambil balance dari dashboard
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

// âš”ï¸ Serang monster
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
    echo "[". date("H:i:s"). "] \033[1;32mâš”ï¸ Serangan dihantar.\033[0m\n";
    echo "[". date("H:i:s"). "] ðŸ“© Respons: ". trim(strip_tags($response)). "\n";
}

// ðŸŽ Klaim reward
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
    echo "[". date("H:i:s"). "] \033[1;33mðŸŽ Reward dituntut.\033[0m\n";
    echo "[". date("H:i:s"). "] ðŸ“© Respons: ". trim(strip_tags($response)). "\n";
}

// ðŸƒ Klaim kartu
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
        echo "[". date("H:i:s"). "] \033[1;35mðŸƒ Kartu ditemukan! Mengklaim...\033[0m\n";
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
        echo "[". date("H:i:s"). "] ðŸŽ‰ Kartu diklaim.\n";
        echo "[". date("H:i:s"). "] ðŸ“© Respons: ". trim(strip_tags($response)). "\n";
}
}

// ðŸ’¸ Auto withdraw
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
    echo "[". date("H:i:s"). "] \033[1;36mðŸ’¸ Auto WD ke FaucetPay dihantar.\033[0m\n";
    echo "[". date("H:i:s"). "] ðŸ“© Respons: ". trim(strip_tags($response)). "\n";
}

// ðŸ” Loop utama
while (true) {
    echo "\n[". date("H:i:s"). "] \033[1;35mðŸ”„ Kitaran bermula...\033[0m\n";

    $hp = getMonsterHP($statusUrl, $cookie, $userAgent, $referer);
    echo "[". date("H:i:s"). "] â¤ï¸ HP Monster: \033[1;36m$hp\033[0m\n";

    if ($hp> 0) {
        attackMonster($attackUrl, $cookie, $userAgent, $referer);
} else {
        claimReward($rewardUrl, $cookie, $userAgent, $referer);
}

    claimInventoryCard($statusUrl, $claimCardUrl, $cookie, $userAgent, $referer);

    $balance = getBalance($statusUrl, $cookie, $userAgent, $referer);
    echo "[". date("H:i:s"). "] ðŸ’° Balance: \033[1;32m$balance LTC\033[0m\n";

    if ($balance>= 0.00005) {
        autoWithdraw($withdrawUrl, $cookie, $userAgent, $referer);
}

    sleep(60); // â„1¤7 Tunggu 1 minit sebelum ulang

}

