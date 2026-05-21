<!DOCTYPE html>
<html>

<head>
    <title>Gemini PHP Test</title>
    <style>
        body {
            font-family: monospace;
            background: #111;
            color: #eee;
            padding: 30px;
            max-width: 700px
        }

        .ok {
            color: #4ade80
        }

        .err {
            color: #f87171
        }

        .warn {
            color: #fbbf24
        }

        .box {
            background: #1e1e1e;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            border: 1px solid #333
        }

        pre {
            background: #0a0a0a;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            color: #aaa;
            overflow-x: auto
        }
    </style>
</head>

<body>
    <h2>🧀 Gemini PHP Diagnostic</h2>

    <?php
    include 'db_config.php';
    $API_KEY = GEMINI_API_KEY;
    
    // ── Step 1: List ALL available models for your key ───────────
    echo '<div class="box">';
    echo '<b>Available Models for your API key:</b><br><br>';

    $listUrl = "https://generativelanguage.googleapis.com/v1beta/models?key={$API_KEY}";
    $ch = curl_init($listUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $workingModel = '';
    if ($code === 200) {
        $data = json_decode($res, true);
        $models = $data['models'] ?? [];
        foreach ($models as $m) {
            $name = $m['name'] ?? '';
            $methods = implode(', ', $m['supportedGenerationMethods'] ?? []);
            if (strpos($methods, 'generateContent') !== false) {
                $short = str_replace('models/', '', $name);
                echo '<span class="ok">✅ ' . $short . '</span> — ' . $methods . '<br>';
                if (!$workingModel && strpos($short, 'flash') !== false)
                    $workingModel = $short;
            }
        }
    } else {
        echo '<span class="err">❌ Could not list models: HTTP ' . $code . '</span>';
        echo '<pre>' . htmlspecialchars($res) . '</pre>';
    }
    echo '</div>';

    // ── Step 2: Test the first working flash model ────────────────
    echo '<div class="box">';
    if ($workingModel) {
        echo "<b>Testing: $workingModel</b><br><br>";
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$workingModel}:generateContent?key={$API_KEY}";
        $payload = json_encode(['contents' => [['role' => 'user', 'parts' => [['text' => 'Reply: CHEESYBOT WORKING']]]], 'generationConfig' => ['maxOutputTokens' => 20]]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 15]);
        $r2 = curl_exec($ch);
        $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code2 === 200) {
            $d = json_decode($r2, true);
            $reply = $d['candidates'][0]['content']['parts'][0]['text'] ?? '';
            echo '<span class="ok">✅ SUCCESS! Use this model name: <b>' . $workingModel . '</b></span><br>';
            echo '<span class="ok">Reply: ' . htmlspecialchars(trim($reply)) . '</span>';
        } else {
            echo '<span class="err">❌ HTTP ' . $code2 . '</span><pre>' . htmlspecialchars($r2) . '</pre>';
        }
    } else {
        echo '<span class="warn">⚠️ No flash model found — check the list above and pick one manually</span>';
    }
    echo '</div>';
    ?>
</body>

</html>