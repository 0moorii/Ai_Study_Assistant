<?php
$result = '';
$error = '';
$promptShown = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic = trim($_POST['topic'] ?? '');
    $mode = $_POST['mode'] ?? 'zero';
    $task = $_POST['task'] ?? 'explain';

    if ($topic === '') {
        $error = 'Please enter a topic.';
    } else {
        $taskInstruction = match ($task) {
            'summary' => "Summarize the following topic clearly in 5 short bullet points: {$topic}",
            'quiz' => "Create 5 multiple-choice questions about {$topic}. Give 4 options (A-D) for each question and put the answer key at the end.",
            default => "Explain the following topic in simple terms for a university student: {$topic}",
        };

        if ($mode === 'one') {
            $prompt = "Example style:\nTopic: DHCP\nAnswer: DHCP automatically gives devices IP configuration so they can communicate on a network.\n\nNow follow the same simple and concise style.\n{$taskInstruction}";
        } else {
            $prompt = $taskInstruction;
        }

        $promptShown = $prompt;

        $apiKey = getenv('OPENAI_API_KEY');
        if (!$apiKey) {
            $error = 'OPENAI_API_KEY is not set on the server.';
        } else {
            $payload = [
                'model' => 'gpt-5.6-luna',
                'input' => $prompt
            ];

            $ch = curl_init('https://api.openai.com/v1/responses');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 45,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || $curlError) {
                $error = 'Connection error: ' . $curlError;
            } else {
                $data = json_decode($response, true);

                if ($httpCode >= 400) {
                    $error = $data['error']['message'] ?? ('API request failed with HTTP ' . $httpCode);
                } else {
                    $pieces = [];
                    foreach (($data['output'] ?? []) as $item) {
                        foreach (($item['content'] ?? []) as $content) {
                            if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                                $pieces[] = $content['text'];
                            }
                        }
                    }
                    $result = trim(implode("\n", $pieces));
                    if ($result === '') {
                        $error = 'The API returned no text output.';
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Study Assistant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card">
    <h1>AI Study Assistant</h1>
    <p class="subtitle">Cloud-based Generative AI demo with zero-shot and one-shot prompting.</p>

    <form method="post">
        <label for="topic">Topic or question</label>
        <textarea id="topic" name="topic" placeholder="Example: Explain what DNS does" required><?= htmlspecialchars($_POST['topic'] ?? '') ?></textarea>

        <div class="grid">
            <div>
                <label for="task">Task</label>
                <select id="task" name="task">
                    <option value="explain" <?= (($_POST['task'] ?? '') === 'explain') ? 'selected' : '' ?>>Explain</option>
                    <option value="summary" <?= (($_POST['task'] ?? '') === 'summary') ? 'selected' : '' ?>>Summarize</option>
                    <option value="quiz" <?= (($_POST['task'] ?? '') === 'quiz') ? 'selected' : '' ?>>Generate Quiz</option>
                </select>
            </div>
            <div>
                <label for="mode">Prompt style</label>
                <select id="mode" name="mode">
                    <option value="zero" <?= (($_POST['mode'] ?? '') === 'zero') ? 'selected' : '' ?>>Zero-shot</option>
                    <option value="one" <?= (($_POST['mode'] ?? '') === 'one') ? 'selected' : '' ?>>One-shot</option>
                </select>
            </div>
        </div>

        <button type="submit">Generate</button>
    </form>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($promptShown): ?>
        <details>
            <summary>Show prompt sent to the AI</summary>
            <pre><?= htmlspecialchars($promptShown) ?></pre>
        </details>
    <?php endif; ?>

    <?php if ($result): ?>
        <div class="result">
            <h2>AI Response</h2>
            <div><?= nl2br(htmlspecialchars($result)) ?></div>
        </div>
    <?php endif; ?>

    <div class="requirements">
        <strong>Requirements demonstrated:</strong>
        <ul>
            <li>Cloud-based generative AI via OpenAI API</li>
            <li>Multiple example prompts</li>
            <li>Zero-shot prompting</li>
            <li>One-shot prompting</li>
        </ul>
    </div>
</div>
</body>
</html>
