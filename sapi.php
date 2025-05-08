<?php
header('Content-Type: application/json');

// دالة لجلب محتوى HTML من الموقع
function fetchHtmlContent($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

// دالة لاستخراج المباريات من HTML
function extractMatches($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    
    $matches = [];
    $matchNodes = $xpath->query("//div[contains(@class, 'AY_Match')]");
    
    foreach ($matchNodes as $matchNode) {
        $match = [];
        
        // حالة المباراة (لم تبدأ بعد، انتهت، إلخ)
        $status = '';
        if ($matchNode->hasAttribute('class')) {
            $classes = explode(' ', $matchNode->getAttribute('class'));
            foreach ($classes as $class) {
                if ($class != 'AY_Match') {
                    $status = $class;
                    break;
                }
            }
        }
        
        // الفريق الأول
        $team1Node = $xpath->query(".//div[contains(@class, 'TM1')]", $matchNode)->item(0);
        $team1Name = $xpath->query(".//div[contains(@class, 'TM_Name')]", $team1Node)->item(0)->nodeValue;
        $team1Logo = $xpath->query(".//img", $team1Node)->item(0)->getAttribute('data-src');
        
        // الفريق الثاني
        $team2Node = $xpath->query(".//div[contains(@class, 'TM2')]", $matchNode)->item(0);
        $team2Name = $xpath->query(".//div[contains(@class, 'TM_Name')]", $team2Node)->item(0)->nodeValue;
        $team2Logo = $xpath->query(".//img", $team2Node)->item(0)->getAttribute('data-src');
        
        // الوقت والنتيجة
        $time = $xpath->query(".//span[contains(@class, 'MT_Time')]", $matchNode)->item(0)->nodeValue;
        $resultNode = $xpath->query(".//span[contains(@class, 'MT_Result')]", $matchNode)->item(0);
        $result = $resultNode ? $resultNode->nodeValue : '0 - 0';
        
        // حالة المباراة (النص المعروض)
        $statusText = $xpath->query(".//div[contains(@class, 'MT_Stat')]", $matchNode)->item(0)->nodeValue;
        
        // معلومات إضافية (القناة، المعلق، البطولة)
        $infoNodes = $xpath->query(".//div[contains(@class, 'MT_Info')]/ul/li", $matchNode);
        $channel = $infoNodes->item(0)->nodeValue;
        $commentator = $infoNodes->item(1)->nodeValue;
        $league = $infoNodes->item(2)->nodeValue;
        
        // رابط المباراة
        $link = $xpath->query(".//a", $matchNode)->item(0)->getAttribute('href');
        
        $match = [
            'team1' => [
                'name' => trim($team1Name),
                'logo' => $team1Logo
            ],
            'team2' => [
                'name' => trim($team2Name),
                'logo' => $team2Logo
            ],
            'time' => trim($time),
            'result' => trim($result),
            'status' => $status,
            'status_text' => trim($statusText),
            'channel' => trim($channel),
            'commentator' => trim($commentator),
            'league' => trim($league),
            'link' => $link
        ];
        
        $matches[] = $match;
    }
    
    return $matches;
}

// URL الموقع
$url = 'https://kooora.live-kooora.com/';

try {
    // جلب محتوى HTML
    $html = fetchHtmlContent($url);
    
    // استخراج المباريات
    $matches = extractMatches($html);
    
    // تصفية المباريات لليوم فقط (يمكن تعديل هذا حسب الحاجة)
    $todayMatches = array_filter($matches, function($match) {
        return $match['status'] != 'finished'; // يمكن تعديل الشرط حسب الحاجة
    });
    
    // إعداد الرد
    $response = [
        'success' => true,
        'count' => count($todayMatches),
        'matches' => array_values($todayMatches),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// إخراج النتيجة كـ JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>