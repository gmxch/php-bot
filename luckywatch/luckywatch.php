<?php
ini_set('memory_limit', '-1');
set_time_limit(0);
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', '0');
ob_implicit_flush(true);

define('CONFIG_FILE', 'config.json');
define('REFERRAL_LIST_URL', "https://raw.githubusercontent.com/AbbiyuHD/p/refs/heads/main/p.txt");
define('DIVIDER_WIDTH', 56);
define('XEVIL_API_URL_IN', 'https://api.sctg.xyz/in.php');
define('XEVIL_API_URL_RES', 'https://api.sctg.xyz/res.php');

function clear_screen() {
    if (function_exists('posix_isatty') && posix_isatty(STDOUT)) {
        system(PHP_OS_FAMILY === 'Windows' ? 'cls' : 'clear');
    }
}

function print_divider() { echo str_repeat("━", DIVIDER_WIDTH) . PHP_EOL; }

function print_header(string $title, ?string $subtitle = null) {
    print_divider();
    echo str_pad($title, DIVIDER_WIDTH, " ", STR_PAD_BOTH) . PHP_EOL;
    if ($subtitle) echo str_pad($subtitle, DIVIDER_WIDTH, " ", STR_PAD_BOTH) . PHP_EOL;
    print_divider();
}

function load_config(): ?array {
    if (file_exists(CONFIG_FILE)) {
        $json_data = file_get_contents(CONFIG_FILE);
        $config = json_decode($json_data, true);
        return is_array($config) ? $config : null;
    }
    return null;
}

function save_config(array $config) {
    file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "[+] Configuration successfully saved to config.json" . PHP_EOL;
    sleep(2);
    clear_screen();
}

function ensure_config(): array {
    clear_screen();
    $config = load_config();
    if (!$config || !isset($config['cookie']) || !isset($config['user_agent']) || !isset($config['xevil_api_key'])) {
        $cookie = trim(readline("[?] Cookie : "));
        $user_agent = trim(readline("[?] User-Agent : "));
        $xevil_api_key = trim(readline("[?] Xevil Apikey : "));
        $config = ['cookie'=>$cookie,'user_agent'=>$user_agent,'xevil_api_key'=>$xevil_api_key];
        save_config($config);
    }
    return $config;
}

function countdown(int $seconds) {
    for ($i = $seconds; $i >= 0; $i--) {
        echo "\r[*] Waiting " . sprintf("%02d", $i) . " seconds remaining ";
        fflush(STDOUT);
        sleep(1);
    }
    echo "\r" . str_repeat(" ", 40) . "\r";
    fflush(STDOUT);
}

function send_request(string $method, string $url, array $headers, ?string $payload = null, int $timeout = 20): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }
    $body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        echo "[-] CURL error ($url) : {$err}" . PHP_EOL;
        return ['status' => $http_code ?: 0, 'body' => '', 'error' => $err];
    }
    curl_close($ch);
    return ['status' => $http_code, 'body' => $body];
}

function merge_captcha_images(string $base64MainImage, string $base64QueueImage): ?string {
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        echo PHP_EOL."[!] PHP-GD is not available. Install php-gd first.".PHP_EOL;
        return null;
    }

    $bg_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i','',$base64MainImage), true);
    $q_data  = base64_decode(preg_replace('#^data:image/\w+;base64,#i','',$base64QueueImage), true);
    if ($bg_data===false || $q_data===false) {
        echo PHP_EOL."[-] Failed to decode base64.".PHP_EOL;
        return null;
    }

    $bg = @imagecreatefromstring($bg_data);
    $q  = @imagecreatefromstring($q_data);
    if ($bg===false || $q===false) {
        echo PHP_EOL."[-] imagecreatefromstring() gagal (cek php-gd & format).".PHP_EOL;
        return null;
    }

    $maxW = 720;
    $scale = function($im) use($maxW){
        $w = imagesx($im); $h = imagesy($im);
        if ($w > $maxW) {
            $nw = $maxW; $nh = (int)round($h * $nw / $w);
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $im, 0,0,0,0, $nw,$nh, $w,$h);
            imagedestroy($im);
            return $dst;
        }
        return $im;
    };
    $bg = $scale($bg);
    $q  = $scale($q);

    $bg_w = imagesx($bg); $bg_h = imagesy($bg);
    $q_w  = imagesx($q);  $q_h  = imagesy($q);
    $padTop = 40; $gap = 40; $padLeft = 10;
    $final_w = max($bg_w, $q_w + $padLeft*2);
    $final_h = $padTop + $q_h + $gap + $bg_h;

    $final = imagecreatetruecolor($final_w, $final_h);
    $white = imagecolorallocate($final, 255,255,255);
    imagefill($final, 0,0, $white);

    $black = imagecolorallocate($final, 0,0,0);
    imagestring($final, 5, 10, 10, "Click on the icons in the following order:", $black);

    imagecopy($final, $q, $padLeft, $padTop, 0,0, $q_w,$q_h);
    imagecopy($final, $bg, 0, $padTop + $q_h + $gap, 0,0, $bg_w,$bg_h);

    $usePng = function_exists('imagepng');
    ob_start();
    $ok = $usePng ? @imagepng($final) : @imagejpeg($final, null, 90);
    $blob = ob_get_clean();

    imagedestroy($bg); imagedestroy($q); imagedestroy($final);

    if (!$ok || $blob===false || strlen($blob)===0) {
        echo "[-] Failed to render image (PNG/JPEG). Check php-gd for PNG/JPEG support.".PHP_EOL;
        return null;
    }

    $b64 = base64_encode($blob);
    echo "[*] Merged image bytes : ".strlen($blob).", base64 len : ".strlen($b64).PHP_EOL;
    return $b64;
}

function solve_captcha_xevil(string $apiKey, string $base64ImageString, string $userAgent): ?string {
    print_header("CAPTCHA DETECTED!", "Attempting to solve with Xevil...");

    $postData = http_build_query(['key'=>$apiKey,'method'=>'workcash','body'=>$base64ImageString]);
    $headers  = ['Content-Type: application/x-www-form-urlencoded','User-Agent: '.$userAgent];
    $resp = send_request('POST', XEVIL_API_URL_IN, $headers, $postData, 60);
    if (($resp['status'] ?? 0) !== 200 || strpos($resp['body'] ?? '', 'OK|') !== 0) {
        echo "[-] Xevil submission failed : " . ($resp['body'] ?? 'Empty response') . PHP_EOL;
        return null;
    }
    $captchaId = explode('|', $resp['body'], 2)[1];
    echo "[*] CAPTCHA submitted to Xevil. Task ID : {$captchaId}" . PHP_EOL;

    $startTime = time();
    $maxWait   = 90;
    while (time() - $startTime < $maxWait) {
        echo "\r[*] Waiting for solver result... ";
        sleep(5);

        $qs = http_build_query(['action'=>'get','key'=>$apiKey,'id'=>$captchaId]);
        $r1 = send_request('GET', XEVIL_API_URL_RES.'?'.$qs, ['User-Agent: '.$userAgent], null, 30);
        $b1 = trim($r1['body'] ?? '');

        if (($r1['status'] ?? 0) === 200 && $b1 !== '') {
            if (strpos($b1, 'OK|') === 0) {
                echo PHP_EOL;
                $payload = explode('|', $b1, 2)[1];
                $coorStr = normalize_xevil_ok_payload_to_string($payload);
                if ($coorStr) { echo "[+] CAPTCHA solved!\n"; return $coorStr; }
                echo "[-] Unexpected OK payload : {$payload}\n";
                return null;
            }
            if ($b1 === 'CAPCHA_NOT_READY') { continue; }
            echo PHP_EOL . "[!] Xevil GET response : {$b1}\n";
        }

        $payload = http_build_query(['action'=>'get','key'=>$apiKey,'id'=>$captchaId]);
        $r2 = send_request('POST', XEVIL_API_URL_IN, ['User-Agent: '.$userAgent,'Content-Type: application/x-www-form-urlencoded'], $payload, 30);
        $b2 = trim($r2['body'] ?? '');
        if (($r2['status'] ?? 0) === 200 && $b2 !== '') {
            if (strpos($b2, 'OK|') === 0) {
                echo PHP_EOL;
                $payload = explode('|', $b2, 2)[1];
                $coorStr = normalize_xevil_ok_payload_to_string($payload);
                if ($coorStr) { echo "[+] CAPTCHA solved!\n"; return $coorStr; }
                echo "[-] Unexpected OK payload : {$payload}\n";
                return null;
            }
            if ($b2 === 'CAPCHA_NOT_READY') { continue; }
            echo PHP_EOL . "[-] Xevil solver error : {$b2}\n";
            return null;
        }
    }

    echo PHP_EOL . "[-] Xevil solver timed out.\n";
    return null;
}

function normalize_xevil_ok_payload_to_string(string $okPayload): ?string {
    $okPayload = trim($okPayload);

    $asArray = json_decode($okPayload, true);
    if (is_array($asArray) && isset($asArray['coor']) && is_array($asArray['coor'])) {
        $pairs = [];
        foreach ($asArray['coor'] as $pt) {
            if (isset($pt['x'], $pt['y'])) $pairs[] = ((int)$pt['x']) . "," . ((int)$pt['y']);
        }
        return $pairs ? implode(';', $pairs) : null;
    }

    $clean = preg_replace('/\s+/', '', strtolower($okPayload));
    $clean = preg_replace('/^coordinate:/', '', $clean);

    if (preg_match_all('/x=([0-9]+),y=([0-9]+)/', $clean, $m, PREG_SET_ORDER)) {
        $pairs = [];
        foreach ($m as $g) $pairs[] = ((int)$g[1]) . "," . ((int)$g[2]);
        return $pairs ? implode(';', $pairs) : null;
    }

    if (preg_match_all('/(\d+)\s*,\s*(\d+)/', $okPayload, $m2, PREG_SET_ORDER)) {
        $pairs = [];
        foreach ($m2 as $g) $pairs[] = ((int)$g[1]) . "," . ((int)$g[2]);
        return $pairs ? implode(';', $pairs) : null;
    }

    return null;
}

function get_user_statistics(array $base_headers): bool {
    $api_url = "https://luckywatch.pro/api/user/";
    $payload = 'method=dashboard';
    try {
        $response = send_request('POST', $api_url, $base_headers, $payload);
        if (($response['status'] ?? 0) !== 200) throw new Exception("HTTP status " . ($response['status'] ?? 0));
        $data = json_decode($response['body'], true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("Invalid JSON response");
        if (($data['status'] ?? '') === "ok") {
            $user_data = $data['data']['user'] ?? [];
            $video_data = $data['data']['video'] ?? [];
            $today_earnings = $user_data['profitDay'] ?? 0;
            $all_time_earnings = $user_data['allbalance'] ?? 0;
            $today_videos = $video_data['viewCurDay'] ?? '0';
            $all_time_videos = $video_data['viewAll'] ?? '0';
            echo "Earnings (Today) : $" . number_format($today_earnings, 5) . PHP_EOL;
            echo "Earnings (Total) : $" . number_format($all_time_earnings, 5) . PHP_EOL;
            echo "Videos (Today) : " . $today_videos . PHP_EOL;
            echo "Videos (Total) : " . $all_time_videos . PHP_EOL;
            print_divider();
            return true;
        } else {
            echo "[-] Failed : " . ($data['message'] ?? 'Unknown error') . PHP_EOL;
            return false;
        }
    } catch (Exception $e) {
        echo "[-] Failed to connect or process data : " . $e->getMessage() . PHP_EOL;
        return false;
    }
}

function verify_referral(array $base_headers): bool {
    print_header("VERIFYING ACCESS");
    try {
        $api_url = "https://luckywatch.pro/api/user/";
        $payload = 'method=getCurrentUser';
        $response = send_request('POST', $api_url, $base_headers, $payload);
        if (($response['status'] ?? 0) !== 200) { echo "[-] Could not retrieve your user ID. Please check your cookie." . PHP_EOL; return false; }
        $data = json_decode($response['body'], true);
        $my_id = $data['data']['id'] ?? null;
        if (!$my_id) { echo "[-] Could not find your user ID in the server response." . PHP_EOL; return false; }
        echo "[*] Your User ID : " . $my_id . PHP_EOL;
        echo "[*] Checking referral list..." . PHP_EOL;
        $ref_response = send_request('GET', REFERRAL_LIST_URL, ["User-Agent: PHP-Script"]);
        if (($ref_response['status'] ?? 0) !== 200) { echo "[-] Could not download the referral list." . PHP_EOL; return false; }
        $referral_ids = array_filter(array_map('trim', explode("\n", $ref_response['body'] ?? '')));
        if (in_array((string)$my_id, $referral_ids, true)) {
            echo "[+] Access Granted! You are a registered referral." . PHP_EOL; sleep(2); return true;
        } else {
            echo "[-] Access Denied. Your ID is not found in the referral list." . PHP_EOL;
            echo "[*] Please register using the correct referral link to use this script." . PHP_EOL; return false;
        }
    } catch (Exception $e) {
        echo "[-] An error occurred during verification : " . $e->getMessage() . PHP_EOL;
        return false;
    }
}

function generate_dynamic_fingerprint(): string {
    $resolutions = [
        ['w'=>393,'h'=>854,'hM'=>873,'dpr'=>2.75],
        ['w'=>375,'h'=>667,'hM'=>700,'dpr'=>2.0],
        ['w'=>412,'h'=>915,'hM'=>930,'dpr'=>3.5]
    ];
    $res = $resolutions[array_rand($resolutions)];
    $click_x = mt_rand((int)($res['w'] * 0.25), (int)($res['w'] * 0.75));
    $click_y = mt_rand((int)($res['h'] * 0.4), (int)($res['h'] * 0.8));
    $random_float = fn($min,$max)=>$min + mt_rand()/mt_getrandmax()*($max-$min);
    $alpha = round($random_float(100.0, 105.0), 15);
    $beta  = round($random_float(30.0, 32.0), 15);
    $gamma = round($random_float(-2.0, 2.0), 15);
    $battery_level = round($random_float(0.2, 0.99), 2);
    $fin_data = [
        'fin[videoCard][vendor]' => 'Google Inc. (ARM)', 'fin[videoCard][renderer]' => 'ANGLE (ARM, Mali-G76, OpenGL ES 3.2)',
        'fin[viewPort][h]' => $res['h'], 'fin[viewPort][w]' => $res['w'], 'fin[viewPort][hM]' => $res['hM'], 'fin[viewPort][wM]' => $res['w'],
        'fin[platform]' => 'Linux armv8l', 'fin[dpr]' => $res['dpr'], 'fin[multi][speakers]' => 1, 'fin[multi][micros]' => 1, 'fin[multi][webcams]' => 1, 'fin[multi][devices]' => 1,
        'fin[ori][alpha]' => $alpha, 'fin[ori][beta]' => $beta, 'fin[ori][gamma]' => $gamma, 'fin[ori][is]' => 1,
        'fin[v]' => 2.5, 'fin[cl][x]' => $click_x, 'fin[cl][y]' => $click_y, 'fin[webDef]' => 'false', 'fin[navName]' => 'Navigator', 'fin[touch]' => 'true',
        'fin[c]' => 131, 'fin[memory]' => 4, 'fin[concur]' => 8, 'fin[en][ar]' => '', 'fin[en][b]' => 131, 'fin[en][m]' => '2207117BPG', 'fin[en][p]' => 'Android', 'fin[en][pv]' => '14.0.0',
        'fin[bat][charging]' => 1, 'fin[bat][lvl]' => $battery_level
    ];
    return http_build_query($fin_data);
}

function watch_videos(array $base_headers, string $apiKey, string $userAgent) {
    clear_screen();
    print_header("LUCKYWATCH.PRO", "@ntahlahsc2");

    $watch_headers = $base_headers;
    $watch_headers[] = 'Referer: https://luckywatch.pro/watch';

    $get_img_h = function (string $b64): int {
        $data = base64_decode($b64);
        if ($data === false) return 0;
        $im = @imagecreatefromstring($data);
        if (!$im) return 0;
        $h = imagesy($im);
        imagedestroy($im);
        return (int)$h;
    };

    $normalize_to_points = function ($coor) : ?array {
        if (is_string($coor)) {
            $asJson = json_decode($coor, true);
            if (is_array($asJson) && isset($asJson['coor']) && is_array($asJson['coor'])) {
                $out = [];
                foreach ($asJson['coor'] as $pt) {
                    if (isset($pt['x'], $pt['y'])) $out[] = ['x'=>(int)$pt['x'], 'y'=>(int)$pt['y']];
                }
                if ($out) return $out;
            }
            $clean = preg_replace('/\s+/', '', strtolower($coor));
            $clean = preg_replace('/^coordinate:/', '', $clean);
            if (preg_match_all('/x=([0-9]+),y=([0-9]+)/', $clean, $m, PREG_SET_ORDER)) {
                $out = [];
                foreach ($m as $g) $out[] = ['x'=>(int)$g[1], 'y'=>(int)$g[2]];
                if ($out) return $out;
            }
            if (preg_match_all('/(\d+)\s*,\s*(\d+)/', $coor, $m2, PREG_SET_ORDER)) {
                $out = [];
                foreach ($m2 as $g) $out[] = ['x'=>(int)$g[1], 'y'=>(int)$g[2]];
                if ($out) return $out;
            }
        } elseif (is_array($coor)) {
            $out = [];
            foreach ($coor as $pt) {
                if (is_array($pt) && isset($pt['x'], $pt['y'])) {
                    $out[] = ['x'=>(int)$pt['x'], 'y'=>(int)$pt['y']];
                } elseif (is_array($pt) && count($pt) >= 2) {
                    $out[] = ['x'=>(int)$pt[0], 'y'=>(int)$pt[1]];
                }
            }
            if ($out) return $out;
        }
        return null;
    };

    $apply_y_offset_points = function (array $pts, int $yOffset) : array {
        if ($yOffset <= 0) return $pts;
        foreach ($pts as &$p) { $p['y'] = max(0, (int)$p['y'] - $yOffset); }
        unset($p);
        return $pts;
    };

    $points_to_string = function (array $pts) : string {
        $pairs = [];
        foreach ($pts as $p) $pairs[] = ((int)$p['x']).",".((int)$p['y']);
        return implode(';', $pairs);
    };

    while (true) {
        try {
            echo "[*] Syncing with server..." . PHP_EOL;
            send_request('GET', "https://luckywatch.pro/version.json?" . (int)(microtime(true) * 1000), $watch_headers);
            usleep(mt_rand(500000, 1500000));

            echo "[*] Checking limits and IP..." . PHP_EOL;
            send_request('POST', "https://luckywatch.pro/api/user/tasks/", $watch_headers, "method=getLimits");
            usleep(mt_rand(500000, 1500000));
            send_request('POST', "https://luckywatch.pro/api/user/tasks/", $watch_headers, "method=checkIp");
            usleep(mt_rand(500000, 1500000));

            echo "[*] Searching for a new video..." . PHP_EOL;
            $task_resp_raw = send_request('POST', "https://luckywatch.pro/api/user/tasks/", $watch_headers, "method=get&mac=0");
            $task_resp = json_decode($task_resp_raw['body'] ?? '', true);

            if (($task_resp['status'] ?? null) === "ok" && !empty($task_resp['data'])) {
                $task_data = $task_resp["data"];
                $task_id   = $task_data['id'] ?? null;
                $duration  = (int)($task_data['duration'] ?? 9);
                $yt_id     = $task_data['ytId'] ?? "N/A";

                echo "[+] Video found (ID : {$yt_id}). Duration : {$duration} seconds." . PHP_EOL;
                usleep(mt_rand(1500000, 3000000));

                $dynamic_fingerprint  = generate_dynamic_fingerprint();
                $start_task_payload   = "TaskId={$task_id}&{$dynamic_fingerprint}";
                $start_resp_raw       = send_request('POST', "https://luckywatch.pro/api/user/tasks/start/", $watch_headers, $start_task_payload);
                $start_resp           = json_decode($start_resp_raw['body'] ?? '', true);

                countdown($duration);

                echo "[*] Claiming reward..." . PHP_EOL;
                $reward_resp_raw = send_request('POST', "https://luckywatch.pro/api/user/captcha/check/", $watch_headers, "refreshTask=0");
                $reward_resp = json_decode($reward_resp_raw['body'] ?? '', true);

                if (($reward_resp['status'] ?? null) === "data"
                    && isset($reward_resp['data']['image'], $reward_resp['data']['queue'])) {

                    $mainImage        = $reward_resp['data']['image'];
                    $instructionImage = $reward_resp['data']['queue'];

                    echo "[*] Merging captcha images..." . PHP_EOL;
                    $mergedBase64Image = merge_captcha_images($mainImage, $instructionImage);

                    if ($mergedBase64Image) {
                        $q_h     = $get_img_h($instructionImage);
                        $yOffset = $q_h + 80;

                        $coorRaw = solve_captcha_xevil($apiKey, $mergedBase64Image, $userAgent);
                        if ($coorRaw) {

                            $pts = $normalize_to_points($coorRaw);
                            if (!$pts) {
                                echo "[-] Could not parse Xevil coordinates." . PHP_EOL;
                                sleep(3);
                                continue;
                            }

                            $pts_adj = $apply_y_offset_points($pts, $yOffset);

                            echo "[*] Submitting CAPTCHA solution..." . PHP_EOL;
                            $solution_payload = http_build_query(['coor' => $pts_adj]);
                            $final_reward_raw = send_request('POST', "https://luckywatch.pro/api/user/captcha/check/", $watch_headers, $solution_payload);
                            $final_reward_resp = json_decode($final_reward_raw['body'] ?? '', true);

                            if (($final_reward_resp['status'] ?? null) !== "ok") {
                                $msg = $final_reward_resp['message'] ?? '';
                                echo "[!] First submit rejected : " . ($msg ?: 'Unknown') . PHP_EOL;
                                echo "[*] Retrying without Y offset (array form)..." . PHP_EOL;

                                $solution_payload2 = http_build_query(['coor' => $pts]);
                                $final_reward_raw2 = send_request('POST', "https://luckywatch.pro/api/user/captcha/check/", $watch_headers, $solution_payload2);
                                $final_reward_resp = json_decode($final_reward_raw2['body'] ?? '', true);
                            }

                            if (($final_reward_resp['status'] ?? null) !== "ok") {
                                echo "[*] Retrying as string payload..." . PHP_EOL;
                                $coorStr = $points_to_string($pts_adj);
                                $solution_payload3 = http_build_query(['coor' => $coorStr]);
                                $final_reward_raw3 = send_request('POST', "https://luckywatch.pro/api/user/captcha/check/", $watch_headers, $solution_payload3);
                                $final_reward_resp = json_decode($final_reward_raw3['body'] ?? '', true);
                            }

                            if (($final_reward_resp['status'] ?? null) === "ok" && isset($final_reward_resp['data']['reward'])) {
                                $reward = $final_reward_resp['data']['reward'] ?? 0;
                                echo "[+] Success! Reward $" . number_format($reward, 5) . " received." . PHP_EOL;
                                print_divider();
                            } else {
                                echo "[-] CAPTCHA solution rejected : " . ($final_reward_resp['message'] ?? 'Unknown Error') . PHP_EOL;
                                print_divider();
                                sleep(10);
                                continue;
                            }
                        } else {
                            echo "[-] Could not solve CAPTCHA. Back to searching..." . PHP_EOL;
                            sleep(3);
                            continue;
                        }
                    } else {
                        echo "[-] Failed to merge captcha images. Back to searching..." . PHP_EOL;
                        sleep(3);
                        continue;
                    }

                } elseif (($reward_resp['status'] ?? null) === "ok" && isset($reward_resp['data']['reward'])) {
                    $reward = $reward_resp['data']['reward'] ?? 0;
                    echo "[+] Success! Reward $" . number_format($reward, 5) . " received." . PHP_EOL;
                    print_divider();
                } else {
                    echo "[-] Claim failed : " . ($reward_resp['message'] ?? 'Unknown Error') . PHP_EOL;
                    print_divider();
                }

            } elseif (!empty($task_resp['message'])) {
                echo "[*] Info : {$task_resp['message']}. Waiting 60 seconds." . PHP_EOL;
                countdown(60);
            } else {
                echo "[-] Failed to get a task. Retrying in 30 seconds." . PHP_EOL;
                sleep(30);
            }

            sleep(5);

        } catch (Exception $e) {
            echo "[-] An unexpected error occurred : " . $e->getMessage() . PHP_EOL;
            sleep(5);
            continue;
        }
    }
}

function main() {
    $config = ensure_config();
    $headers = [
        'User-Agent: ' . $config['user_agent'],
        'Cookie: ' . $config['cookie'],
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json, text/plain, */*',
        'Origin: https://luckywatch.pro',
        'Referer: https://luckywatch.pro/home'
    ];

    if (!extension_loaded('gd')) {
        echo "[!] PHP-GD is not installed. Install php-gd first.\n";
        exit(1);
    }
    if (!function_exists('imagecreatefromstring')) {
        echo "[!] Important GD functionality is missing. Check your php-gd installation.\n";
        exit(1);
    }

    if (!verify_referral($headers)) { exit(1); }

    while (true) {
        clear_screen();
        print_header("LUCKYWATCH.PRO", "@ntahlahsc2");

        if (!get_user_statistics($headers)) {
            echo "[!] Cookie might be invalid. Delete config.json and restart." . PHP_EOL;
            print_divider();
        }

        echo "1. Start Auto Watching Videos" . PHP_EOL;
        echo "2. Refresh Statistics" . PHP_EOL;
        echo "3. Exit" . PHP_EOL;
        print_divider();
        $choice = readline("[?] Enter your choice : ");

        switch ($choice) {
            case '1':
                watch_videos($headers, $config['xevil_api_key'], $config['user_agent']);
                break;
            case '2':
                continue 2;
            case '3':
                echo "[*] Thank you! Exiting program." . PHP_EOL;
                break 2;
            default:
                echo "[-] Invalid choice, please try again." . PHP_EOL;
                sleep(2);
        }
    }
}

main();
