<?php
//system("clear");
error_reporting(0);
date_default_timezone_set("Asia/Jakarta");

$url = "https://feyorra.top";

// input manual tiap run
$cookie = getenv('LOGIN');
//$cookie = readline('cookie: ');
if (!$cookie) {exit;
}

$uagent = file(__DIR__ . '/../USRAGNT.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); 
//$uagent = file('USRAGNT.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); 
$user_agent = $uagent[array_rand($uagent)];
echo "set UA => $user_agent\n";

function curl($url, $cookie, $user_agent, $post = null) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_HTTPHEADER => [
            "Cookie: $cookie",
            "User-Agent: $user_agent",
            "Accept: */*",
            "Referer: https://feyorra.top/faucet"
        ]
    ]);
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

function solvecaptcha($file) {
    exec("convert $file -resize 300x80 -colorspace Gray -threshold 60% $file 2>/dev/null");
    $out = [];
    exec("tesseract $file stdout --psm 8 -c tessedit_char_whitelist=0123456789 2>/dev/null", $out);
    return trim(implode("", $out));
}

function dashboard($cookie, $user_agent) {
    global $url;
    $res = curl("$url/dashboard", $cookie, $user_agent);
    preg_match('/Main Balance<\/h3>\s*<p>(.*?) Coins<\/p>/', $res, $bal);
    preg_match('/Today Earned<\/h3>\s*<p>(.*?) Coins<\/p>/', $res, $today);
    return [
        "balance" => isset($bal[1]) ? $bal[1] : "0",
        "today"   => isset($today[1]) ? $today[1] : "0",
        "raw"     => $res
    ];
}

$data = dashboard($cookie, $user_agent);

$width = 40;
echo str_repeat("━", $width) . "\n";
echo str_pad("FEYORRA.TOP", $width, " ", STR_PAD_BOTH) . "\n";
echo str_pad("@ntahlahsc2", $width, " ", STR_PAD_BOTH) . "\n";
echo str_repeat("━", $width) . "\n";
echo str_pad("Main Balance : {$data['balance']} Coins", $width, " ", STR_PAD_RIGHT) . "\n";
echo str_pad("Today Earned : {$data['today']} Coins", $width, " ", STR_PAD_RIGHT) . "\n";
echo str_repeat("━", $width) . "\n";

while (true) {
    $faucet = curl("$url/faucet", $cookie, $user_agent);

    if (preg_match('/id="countdown"[^>]*>(\d+)<\/span>/', $faucet, $cd)) {
        $wait = $cd[1];
        for ($i = $wait; $i > 0; $i--) {
            echo "\r[!] Waiting $i seconds";
            flush();
            sleep(1);
        }
        echo "\r\033[K";
        continue;
    }

    if (!preg_match('/name="csrf_token_name"[^>]+value="(.*?)"/', $faucet, $csrf)) {
        echo "[!] Please complete 1 shortlink first\n";
        file_put_contents("csrf.html", $faucet);
        exit;
    }
    $csrfToken = $csrf[1];

    if (!preg_match('/name="token" value="(.*?)"/', $faucet, $tk)) {
        echo "[!] Failed to get token!\n";
        file_put_contents("token.html", $faucet);
        exit;
    }
    $token = $tk[1];

    if (!preg_match('/name="([A-Za-z0-9]+)" value=""/', $faucet, $capField)) {
        echo "[!] Failed to detect captcha field name!\n";
        file_put_contents("captcha_field.html", $faucet);
        exit;
    }
    $captchaField = $capField[1];

    if (!preg_match('/src="(https:\/\/feyorra\.top\/assets\/images\/captcha\/.*?)"/', $faucet, $capUrl)) {
        echo "[!] Failed to detect captcha URL!\n";
        file_put_contents("captcha_url.html", $faucet);
        exit;
    }
    $captchaUrl = $capUrl[1];

    $img = curl($captchaUrl, $cookie, $user_agent);
    file_put_contents("captcha.jpg", $img);

    $captcha = "";
    for ($i = 0; $i < 3 && $captcha === ""; $i++) {
        $captcha = solvecaptcha("captcha.jpg");
    }

    $postData = "csrf_token_name=$csrfToken&token=$token&$captchaField=$captcha";
    $verify = curl("$url/faucet/verify", $cookie, $user_agent, $postData);

    if (preg_match("/title:\s*'(\d+)\s+Coins has been added/", $verify, $reward)) {
        $rewarded = $reward[1];
        $data = dashboard($cookie, $user_agent);

        echo "\r\033[K";
        echo str_pad("Reward : {$rewarded} Coins", $width, " ", STR_PAD_RIGHT) . "\n";
        echo str_pad("Balance Update : {$data['balance']} Coins", $width, " ", STR_PAD_RIGHT) . "\n";
        echo str_pad("Today Earned : {$data['today']} Coins", $width, " ", STR_PAD_RIGHT) . "\n";
        echo str_repeat("━", $width) . "\n";

        if (preg_match("/let wait = (\d+)/", $verify, $cd)) {
            $wait = $cd[1];
        } else {
            $wait = 10;
        }
        for ($i = $wait; $i > 0; $i--) {
            echo "\r[!] Waiting $i seconds";
            flush();
            sleep(1);
        }
        echo "\r\033[K";
    } else {
        echo "\r\033[K";
        echo "[!] Claim failed. Retrying...";
        flush();
        sleep(3);
        echo "\r\033[K";
        continue;
    }
}
