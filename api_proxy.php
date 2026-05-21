<?php
// ================================================================
//  api_proxy.php — Google Gemini FREE API Proxy
//  Model: gemini-1.5-flash (stable free tier — 15 RPM)
//  FREE: 1,500 requests/day | No credit card needed
// ================================================================
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

// ================================================================
//  ✅ PASTE YOUR GEMINI API KEY HERE
//  Get free key: https://aistudio.google.com/ → Get API Key
//  Key starts with: AIza...
// ================================================================
define('GEMINI_API_KEY', 'AIzaSyDpUtnwnqAJrkIxFQyql6hPhQOiTBC-KHM');
define('GEMINI_MODEL', 'gemini-2.5-flash');
// ================================================================

// ── Read request body ────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['messages']) || !is_array($body['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request: messages array missing']);
    exit;
}

// ── Separate system prompt and conversation messages ─────────
$systemText = '';
$rawMsgs = [];

foreach ($body['messages'] as $msg) {
    $role = trim($msg['role'] ?? '');
    $content = trim($msg['content'] ?? '');

    if ($role === 'system') {
        $systemText = $content;   // Gemini takes system separately
        continue;
    }
    if (!$role || !$content)
        continue;   // skip empty messages

    $geminiRole = ($role === 'assistant') ? 'model' : 'user';
    $rawMsgs[] = ['role' => $geminiRole, 'text' => $content];
}

// ================================================================
//  FIX FOR API 400:
//  Gemini REQUIRES strictly alternating user → model → user → model
//  If history has two consecutive same-role messages (happens when
//  a previous request errored before saving the assistant reply),
//  we remove the duplicate to restore proper alternation.
// ================================================================
$contents = [];
$lastRole = '';

foreach ($rawMsgs as $m) {
    if ($m['role'] === $lastRole) {
        // Same role twice in a row — replace previous with this one
        // (keeps the most recent message from that role)
        array_pop($contents);
    }
    $contents[] = [
        'role' => $m['role'],
        'parts' => [['text' => $m['text']]]
    ];
    $lastRole = $m['role'];
}

// Gemini: first message must be from 'user'
while (!empty($contents) && $contents[0]['role'] !== 'user') {
    array_shift($contents);
}

// Gemini: last message must be from 'user' (we are asking for a reply)
while (!empty($contents) && end($contents)['role'] !== 'user') {
    array_pop($contents);
}

if (empty($contents)) {
    echo json_encode(['error' => 'No valid user message found']);
    exit;
}

// ── Build Gemini payload ─────────────────────────────────────
$payload = ['contents' => $contents];

if ($systemText) {
    $payload['system_instruction'] = [
        'parts' => [['text' => $systemText]]
    ];
}

$payload['generationConfig'] = [
    'maxOutputTokens' => 900,
    'temperature' => 0.7,
];

// ── Call Gemini with retry on 429 ────────────────────────────
$url = sprintf(
    'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
    GEMINI_MODEL,
    GEMINI_API_KEY
);

$geminiResponse = '';
$httpCode = 0;
$curlErr = '';
$delay = 2;

for ($attempt = 1; $attempt <= 3; $attempt++) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $geminiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 429 && $attempt < 3) {
        sleep($delay);
        $delay *= 2;   // 2s → 4s → 8s
        continue;
    }
    break;
}

if ($curlErr) {
    http_response_code(500);
    echo json_encode(['error' => 'Connection failed: ' . $curlErr]);
    exit;
}

// ── Parse Gemini response ────────────────────────────────────
$geminiData = json_decode($geminiResponse, true);

if ($httpCode !== 200 || !isset($geminiData['candidates'][0]['content']['parts'][0]['text'])) {
    $errMsg = $geminiData['error']['message'] ?? ('Gemini API error — HTTP ' . $httpCode);
    http_response_code($httpCode ?: 500);
    echo json_encode([
        'error' => $errMsg,
        'choices' => [['message' => ['content' => 'Sorry, could not get a response. Please try again.']]]
    ]);
    exit;
}

$replyText = $geminiData['candidates'][0]['content']['parts'][0]['text'];

// Return in OpenAI-compatible format (agent.php expects this)
echo json_encode([
    'choices' => [
        ['message' => ['content' => $replyText]]
    ]
]);
?>