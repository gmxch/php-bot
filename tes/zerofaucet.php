<?php
const host = "https://zerofaucet.com/";

$wallet = getenv('LOGIN');
$uagent = file(__DIR__ . '/../USRAGNT.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); 
$userAgent = $uagent[array_rand($uagent)];
echo "set UA => $userAgent\n";

function cekRecaptchaV3($html, $url = null) {
    if (preg_match('/grecaptcha\.execute\([\'"]([a-zA-Z0-9_-]{40,})[\'"]/', $html, $m)) {
        $sitekey = $m[1];
        echo "[!] reCAPTCHA v3 detected! Sitekey: $sitekey\n";
        $cmd = "node tes/solveRecaptcha.js {$sitekey} {$url}";
        $token = trim(shell_exec($cmd));
        echo "[+] Token received: $token\n";
        return $token;
    }
    return null;
}

function curl_req($method, $url, $headers=[], $data=null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, "cookie.txt");
    curl_setopt($ch, CURLOPT_COOKIEJAR, "cookie.txt");
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $method = strtoupper($method);
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    return curl_exec($ch);
}

class Tesseract {
    private $img="img.png", $frame="frame.png", $cleaned="cleaned.png", $outputFile="hasil", $isWindows;

    public function __construct() {
        $this->isWindows=strtoupper(substr(PHP_OS,0,3))==='WIN';
        if(!$this->isCommandAvailable("tesseract -v")) exit("[!] Tesseract not installed!\n");
        $magick = $this->isWindows ? "magick" : "convert";
        if(!$this->isCommandAvailable("$magick -version")) exit("[!] ImageMagick not installed!\n");
    }
    private function isCommandAvailable($cmd) {
        $null = $this->isWindows ? "2>NUL" : "2>/dev/null";
        return !empty(shell_exec("$cmd $null"));
    }
    public function solve($base64Image) {
        file_put_contents($this->img, base64_decode($base64Image));
        $magick = $this->isWindows ? "magick" : "convert";
        shell_exec("$magick {$this->img} -colorspace Gray {$this->frame}");
        shell_exec("$magick {$this->frame} -morphology Close Octagon -blur 1x1 {$this->cleaned}");
        shell_exec("tesseract {$this->cleaned} {$this->outputFile} -l eng --psm 7");
        return trim(file_get_contents($this->outputFile.'.txt'));
    }
}

function getBalance($html){
    $doc=new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    $xpath=new DOMXPath($doc);
    $node=$xpath->query("//font[contains(text(),'Zatoshi') or contains(text(),'ZER')]");
    return ($node->length>0)?trim($node->item(0)->textContent):null;
}

$tesseract = new Tesseract();
$headers=["host: zerofaucet.com","user-agent: $userAgent"];
$balance_awal = null;

while(true){
    // --- Login page ---
    $url = host."index.php?loginwallet=".$wallet;
    $html = curl_req("GET",$url,$headers);
    $token = cekRecaptchaV3($html,$url);
    $balance = getBalance($html);
    echo "Balance\t\t:: ".($balance??"not found")."\n";
    $balance_awal = $balance ?? $balance_awal;

    // --- Dailygift ---
    if(strpos($html,"dailygift.php")!==false){
        echo "Dailygift\n";
        $img = curl_req("GET",host."captcha.php?loginwallet=".$wallet,array_merge($headers,["referer: ".host."dailygift.php"]));
        $cap = $tesseract->solve(base64_encode($img));
        $r = curl_req("GET",host."dailygift.php?easycaptcha=".$cap."&action=Collect&g-recaptcha-response=".$token,$headers);
        $win = @explode('</font>', @explode('font color="white" size="6" class="msgClass">',$r)[1])[0]??null;
        echo "You win\t\t:: ".$win."\n";
        $html = curl_req("GET",$url,$headers);
        $balance = getBalance($html);
        echo "Balance\t\t:: ".($balance??"not found")."\n";
        $balance_awal = $balance ?? $balance_awal;
    }

    // --- Claim ---
    $res = curl_req("POST",host."index.php?claim=1",$headers,"g-recaptcha-response=".$token);
    if(preg_match('/window.location.href = "([^"]+)"/',$res,$m)) $location=$m[1]; else $location=null;

    if($location){
        $html = curl_req("GET",$location,$headers);
        $token = cekRecaptchaV3($html,$location);

        // --- Follow link_hash or 1ink ---
        if(preg_match('/<a href="https:\/\/zerofaucet.com\/([^"]+)"/',$html,$m2)) {
            $link_hash=$m2[1];
            $html = curl_req("GET",host.$link_hash,$headers);
        } else if(preg_match('/<a id="countingbtn" href="https:\/\/1ink.cc\/go.php\?([^"]+)"/',$html,$m3)){
            $list_data=$m3[1];
            $location = curl_req("POST","https://1ink.cc/api/pass.php","",$list_data."&captcha=");
            $html = curl_req("GET",$location,$headers);
        }

        // --- Confirm reward ---
        if(preg_match('/<a href="index.php\?confirm1=([^"]+)"/',$html,$m4)){
            $confirm=$m4[1];
            $html = curl_req("GET",host."index.php?confirm1=".$confirm,$headers);
        }
        $reward=@explode('</font>',@explode("<font size='6' color='#ffff00'><b>",$html)[1])[0]??null;
        echo "Reward\t\t:: ".$reward."\n";

        $html = curl_req("GET",$url,$headers);
        $balance = getBalance($html);
        echo "Balance\t\t:: ".($balance??"not found")."\n";
        $balance_awal = $balance ?? $balance_awal;
    }

    // --- Enemy attack ---
    if($balance==$balance_awal){
        echo "land under attack!\n";
        $html = curl_req("GET",host."enemy.php",$headers);
        $token = cekRecaptchaV3($html,host."enemy.php");
        $img = curl_req("GET",host."captcha.php?loginwallet=".$wallet,array_merge($headers,["referer: ".host."enemy.php"]));
        $cap = $tesseract->solve(base64_encode($img));
        $html = curl_req("GET",host."enemy.php?easycaptcha=".$cap."&action=Attack",$headers);
    }

    echo "wait 60s\n";
    sleep(60);
}
?>
