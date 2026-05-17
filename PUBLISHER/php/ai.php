<?php


class ai
{

    protected $apiKey, $instructions, $prompt, $lang, $textModel = 'gpt-5.2', $imageModel = 'gpt-image-1.5', $logDbo = null, $logContext = [], $logRequestStartedAt = null;
    protected static $humanWritingInstructions = 'Write as an experienced human writer with natural variation in structure, pacing, tone, and syntax. Avoid predictable AI writing patterns, repetitive phrasing, over-structured formatting, and generic vocabulary. Prioritize authenticity, readability, and stylistic diversity over mechanical consistency. Avoid em dashes, M-dashes, and other AI-like punctuation habits; use commas, parentheses, colons, or shorter sentences instead.';
    protected static $validLogActionTypes = ['suggest_title', 'suggest_summary', 'generate_article', 'rewrite_article', 'generate_tags', 'generate_social_post', 'check_similarity'];

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function instructions($val) {
        $this->instructions = $val;
    }

    public function prompt($val) {
        $this->prompt = $val;
    }

    public function lang($val) {
        $this->lang = $val;
    }

    public function text_model($val) {
        $this->textModel = trim((string)$val) !== '' ? trim((string)$val) : $this->textModel;
    }

    public function image_model($val) {
        $this->imageModel = trim((string)$val) !== '' ? trim((string)$val) : $this->imageModel;
    }

    public function log_context($dbo, array $context = []) {
        $this->logDbo = $dbo;
        $this->logContext = $context;
    }

    private function request_instructions() {
        $instructions = trim((string)$this->instructions);
        $writingInstructions = self::$humanWritingInstructions;
        if ($instructions === '') {
            return $writingInstructions;
        }
        if (strpos($instructions, $writingInstructions) !== false) {
            return $instructions;
        }
        return $instructions . "\n\n" . $writingInstructions;
    }

    private function log_action_type() {
        $actionType = trim((string)($this->logContext['action_type'] ?? 'generate_article'));
        return in_array($actionType, self::$validLogActionTypes, true) ? $actionType : 'generate_article';
    }

    private function estimate_text_cost($model, $inputTokens, $outputTokens) {
        $model = strtolower(trim((string)$model));
        $rates = [
            'gpt-5.4' => ['input' => 1.25, 'output' => 10.00],
            'gpt-5.3' => ['input' => 1.25, 'output' => 10.00],
            'gpt-5.2' => ['input' => 1.25, 'output' => 10.00],
            'gpt-5' => ['input' => 1.25, 'output' => 10.00],
        ];
        $rate = ['input' => 1.25, 'output' => 10.00];
        foreach ($rates as $prefix => $candidate) {
            if (strpos($model, $prefix) === 0) {
                $rate = $candidate;
                break;
            }
        }
        return round((($inputTokens * $rate['input']) + ($outputTokens * $rate['output'])) / 1000000, 4);
    }

    private function estimate_image_cost($model) {
        $model = strtolower(trim((string)$model));
        $rates = [
            'gpt-image-1.5' => 0.0700,
            'gpt-image-1' => 0.0400,
        ];
        foreach ($rates as $prefix => $cost) {
            if (strpos($model, $prefix) === 0) {
                return $cost;
            }
        }
        return 0.0000;
    }

    private function insert_generation_log($model, $prompt, $response, $tokensInput, $tokensOutput, $costEstimate, $status, $errorMessage = null, $durationSeconds = null) {
        if (!$this->logDbo || empty($this->logContext['account_id'])) {
            return;
        }
        if ($durationSeconds === null && $this->logRequestStartedAt !== null) {
            $durationSeconds = round(microtime(true) - $this->logRequestStartedAt, 3);
        }

        try {
            $this->logDbo->execSQL(
                'INSERT INTO ai_generation_logs
                 (account_id, property_id, content_item_id, content_idea_id, action_type, provider, model, prompt, response, tokens_input, tokens_output, cost_estimate, duration_seconds, status, error_message, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int)$this->logContext['account_id'],
                    isset($this->logContext['property_id']) && (int)$this->logContext['property_id'] > 0 ? (int)$this->logContext['property_id'] : null,
                    isset($this->logContext['content_item_id']) && (int)$this->logContext['content_item_id'] > 0 ? (int)$this->logContext['content_item_id'] : null,
                    isset($this->logContext['content_idea_id']) && (int)$this->logContext['content_idea_id'] > 0 ? (int)$this->logContext['content_idea_id'] : null,
                    $this->log_action_type(),
                    'openai',
                    $model,
                    $prompt,
                    $response,
                    $tokensInput !== null ? (int)$tokensInput : null,
                    $tokensOutput !== null ? (int)$tokensOutput : null,
                    $costEstimate !== null ? $costEstimate : null,
                    $durationSeconds !== null ? $durationSeconds : null,
                    $status,
                    $errorMessage,
                    isset($this->logContext['created_by']) && (int)$this->logContext['created_by'] > 0 ? (int)$this->logContext['created_by'] : null,
                    date('Y-m-d H:i:s'),
                ]
            );
        } catch (Throwable $ex) {
        }
    }

    private function logged_text_prompt($instructions, $input) {
        return json_encode([
            'instructions' => $instructions,
            'input' => $input,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function send_request() {
        $this->logRequestStartedAt = microtime(true);
        $requestInstructions = $this->request_instructions();
        $requestPrompt = (string)$this->prompt;
        $loggedPrompt = $this->logged_text_prompt($requestInstructions, $requestPrompt);
        $data = [
            "model" => $this->textModel,
            "instructions" => $requestInstructions,
            "input" => $requestPrompt,
            "reasoning" => [
                "effort" => "medium"
            ],
            "max_output_tokens" => 4000,
        ];

        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));

        // Execute request
        $response = curl_exec($ch);


        // 1. Transport / cURL error?
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            $err_msg = json_encode(['error' => 'cURL error: ' . $err]);
            $this->insert_generation_log($this->textModel, $loggedPrompt, null, null, null, null, 'failed', $err_msg);
            return [
                'result' => 'error',
                'message' => $err_msg,
            ];
        }

        // 2. HTTP status code check
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode < 200 || $httpCode >= 300) {
            // Try to decode any error message from the body
            $body = json_decode($response, true);
            $message = $body['error']['message'] 
                    ?? 'Unexpected HTTP status: ' . $httpCode;
            $err_msg = json_encode([
                'error' => 'API request failed',
                'status' => $httpCode,
                'message' => $message
            ]);
            $this->insert_generation_log($this->textModel, $loggedPrompt, (string)$response, null, null, null, 'failed', $err_msg);
            return [
                'result' => 'error',
                'message' => $err_msg,
            ];
        }

        // 3. Decode full response
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $err_msg = json_encode([
                'error' => 'Invalid JSON in response: ' . json_last_error_msg()
            ]);
            $this->insert_generation_log($this->textModel, $loggedPrompt, (string)$response, null, null, null, 'failed', $err_msg);
            return [
                'result' => 'error',
                'message' => $err_msg,
            ];
        }

        // 4. API-level error payload?
        if (isset($result['error'])) {
            $err = $result['error'];
            $err_msg = json_encode([
                'error'   => 'OpenAI API error',
                'type'    => $err['type']    ?? 'unknown',
                'message' => $err['message'] ?? 'No message provided',
                'code'    => $err['code']    ?? null
            ]);
            $this->insert_generation_log($this->textModel, $loggedPrompt, json_encode($result, JSON_UNESCAPED_UNICODE), null, null, null, 'failed', $err_msg);
            return [
                'result' => 'error',
                'message' => $err_msg,
            ];
        }

        // 5. Extract content safely
        $content = $this->extract_response_text($result);
        if ($content === '') {
            $err_msg = json_encode(['error' => 'Empty content in API response']);
            $this->insert_generation_log($this->textModel, $loggedPrompt, json_encode($result, JSON_UNESCAPED_UNICODE), null, null, null, 'failed', $err_msg);
            return [
                'result' => 'error',
                'message' => $err_msg,
            ];
        }

        // Parse the JSON from the AI output
        // Remove ```json and ``` if present
        $cleaned = trim($content);
        $cleaned = preg_replace('/^```json\s*/', '', $cleaned); // Remove starting ```json
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);     // Remove ending ```

        $ai_content = $cleaned;

        $inputTokens = $result['usage']['input_tokens'] ?? 0;
        $outputTokens = $result['usage']['output_tokens'] ?? 0;
        // Token usage returned by the Responses API.
        $totalTokens = $result['usage']['total_tokens'] ?? ($inputTokens + $outputTokens);
        $costEstimate = $this->estimate_text_cost($this->textModel, $inputTokens, $outputTokens);
        $ai_cost = "<p>{$this->textModel} tokens: input {$inputTokens}, output {$outputTokens}, total {$totalTokens}</p>";
        $this->insert_generation_log($this->textModel, $loggedPrompt, $ai_content, $inputTokens, $outputTokens, $costEstimate, 'success');

        $response = [
            "result" => 'success',
            "content" => $ai_content,
            "cost" => $ai_cost,
            "cost_estimate" => $costEstimate,
            "tokens_input" => $inputTokens,
            "tokens_output" => $outputTokens,
            "tokens_total" => $totalTokens,
        ];
        return $response;

    }

    private function extract_response_text($result) {
        if (!empty($result['output_text'])) {
            return (string)$result['output_text'];
        }

        if (empty($result['output']) || !is_array($result['output'])) {
            return '';
        }

        $parts = [];
        foreach ($result['output'] as $outputItem) {
            if (empty($outputItem['content']) || !is_array($outputItem['content'])) {
                continue;
            }
            foreach ($outputItem['content'] as $contentItem) {
                if (isset($contentItem['text'])) {
                    $parts[] = (string)$contentItem['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }


    private function generate_openai_image() {
        $this->logRequestStartedAt = microtime(true);
        $endpoint = 'https://api.openai.com/v1/images/generations';
        $data = [
            "model" => $this->imageModel,
            'prompt' => $this->prompt,
            'n'      => 1,
            'size'   => '1536x1024',
            'quality' => 'medium',
            'output_format' => 'jpeg',
            'output_compression' => 85,
        ];
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS     => json_encode($data)
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $message = 'OpenAI API request error: ' . curl_error($ch);
            $this->insert_generation_log($this->imageModel, (string)$this->prompt, null, null, null, $this->estimate_image_cost($this->imageModel), 'failed', $message);
            throw new Exception($message);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = json_decode($response, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = is_array($body) ? ($body['error']['message'] ?? 'Unexpected HTTP status: ' . $httpCode) : ('Unexpected HTTP status: ' . $httpCode);
            $this->insert_generation_log($this->imageModel, (string)$this->prompt, (string)$response, null, null, $this->estimate_image_cost($this->imageModel), 'failed', $message);
            throw new Exception('OpenAI image generation failed: ' . $message);
        }
        if (!is_array($body)) {
            $message = 'Invalid OpenAI image generation response: ' . $response;
            $this->insert_generation_log($this->imageModel, (string)$this->prompt, (string)$response, null, null, $this->estimate_image_cost($this->imageModel), 'failed', $message);
            throw new Exception('Invalid OpenAI image generation response: ' . $response);
        }

        if (isset($body['data'][0]['b64_json'])) {
            $imageBytes = base64_decode($body['data'][0]['b64_json'], true);
            if ($imageBytes === false) {
                $message = 'Failed to decode OpenAI image data.';
                $this->insert_generation_log($this->imageModel, (string)$this->prompt, json_encode(['response_shape' => 'b64_json'], JSON_UNESCAPED_UNICODE), null, null, $this->estimate_image_cost($this->imageModel), 'failed', $message);
                throw new Exception('Failed to decode OpenAI image data.');
            }
            $this->insert_generation_log(
                $this->imageModel,
                (string)$this->prompt,
                json_encode(['response_shape' => 'b64_json', 'bytes' => strlen($imageBytes), 'mime_type' => 'image/' . ($body['output_format'] ?? 'jpeg')], JSON_UNESCAPED_UNICODE),
                null,
                null,
                $this->estimate_image_cost($this->imageModel),
                'success'
            );
            return [
                'data' => $imageBytes,
                'type' => 'image/' . ($body['output_format'] ?? 'jpeg'),
            ];
        }

        if (isset($body['data'][0]['url'])) {
            $image = $this->download_image_data($body['data'][0]['url']);
            $this->insert_generation_log(
                $this->imageModel,
                (string)$this->prompt,
                json_encode(['response_shape' => 'url', 'bytes' => strlen($image['data'] ?? ''), 'mime_type' => $image['type'] ?? null], JSON_UNESCAPED_UNICODE),
                null,
                null,
                $this->estimate_image_cost($this->imageModel),
                'success'
            );
            return $image;
        }

        if (isset($body['output'][0]['result'])) {
            $imageBytes = base64_decode($body['output'][0]['result'], true);
            if ($imageBytes === false) {
                $message = 'Failed to decode OpenAI image data.';
                $this->insert_generation_log($this->imageModel, (string)$this->prompt, json_encode(['response_shape' => 'output_result'], JSON_UNESCAPED_UNICODE), null, null, $this->estimate_image_cost($this->imageModel), 'failed', $message);
                throw new Exception('Failed to decode OpenAI image data.');
            }
            $this->insert_generation_log(
                $this->imageModel,
                (string)$this->prompt,
                json_encode(['response_shape' => 'output_result', 'bytes' => strlen($imageBytes), 'mime_type' => 'image/jpeg'], JSON_UNESCAPED_UNICODE),
                null,
                null,
                $this->estimate_image_cost($this->imageModel),
                'success'
            );
            return [
                'data' => $imageBytes,
                'type' => 'image/jpeg',
            ];
        }

        if (isset($body['output'])) {
            foreach ($body['output'] as $output) {
                if (($output['type'] ?? '') === 'image_generation_call' && isset($output['result'])) {
                    $imageBytes = base64_decode($output['result'], true);
                    if ($imageBytes === false) {
                        $message = 'Failed to decode OpenAI image data.';
                        $this->insert_generation_log($this->imageModel, (string)$this->prompt, json_encode(['response_shape' => 'image_generation_call'], JSON_UNESCAPED_UNICODE), null, null, $this->estimate_image_cost($this->imageModel), 'failed', $message);
                        throw new Exception('Failed to decode OpenAI image data.');
                    }
                    $this->insert_generation_log(
                        $this->imageModel,
                        (string)$this->prompt,
                        json_encode(['response_shape' => 'image_generation_call', 'bytes' => strlen($imageBytes), 'mime_type' => 'image/jpeg'], JSON_UNESCAPED_UNICODE),
                        null,
                        null,
                        $this->estimate_image_cost($this->imageModel),
                        'success'
                    );
                    return [
                        'data' => $imageBytes,
                        'type' => 'image/jpeg',
                    ];
                }
            }
        }

        if (!isset($body['data'][0])) {
            $message = 'Unexpected OpenAI response: ' . $response;
            $this->insert_generation_log($this->imageModel, (string)$this->prompt, (string)$response, null, null, $this->estimate_image_cost($this->imageModel), 'failed', $message);
            throw new Exception('Unexpected OpenAI response: ' . $response);
        }

        // $usage = $body['usage'];
        // echo "Prompt tokens: {$usage['prompt_tokens']}<br/>";
        // echo "Completion tokens: {$usage['completion_tokens']}<br/>";
        // echo "Total tokens: {$usage['total_tokens']}<br/>";

        $message = 'OpenAI image generation response did not include image data.';
        $this->insert_generation_log($this->imageModel, (string)$this->prompt, (string)$response, null, null, $this->estimate_image_cost($this->imageModel), 'failed', $message);
        throw new Exception($message);

    }

    private function download_image_data($imageUrl) {
        $ch = curl_init($imageUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Image download error: ' . curl_error($ch));
        }
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        return ['data' => $data, 'type' => $contentType];
    }


    /**
     * Generate an image via OpenAI and save it to $directory.
     *
     * @param  string  $directory  Path to an existing (or creatable) folder, e.g. '/var/www/images'
     * @return string              Full path of the saved file.
     * @throws Exception           On any download, write, or directory error.
     */
    public function create_image(string $directory): string
    {
        // 1. Generate & download
        $imageData = $this->generate_openai_image();

        // 2. Normalize & ensure directory exists
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new Exception("Could not create directory: {$directory}");
        }
        if (!is_writable($directory)) {
            throw new Exception("Directory is not writable: {$directory}");
        }

        // 3. Create GD image resource from raw data
        $img = imagecreatefromstring($imageData['data']);
        if ($img === false) {
            throw new Exception('Failed to parse image data into GD resource.');
        }

        // 4. Generate a unique .jpg filename
        $filename = uniqid('openai_img_', true) . '.jpg';
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        // 5. Save as JPEG @80% quality
        if (! imagejpeg($img, $fullPath, 80) ) {
            imagedestroy($img);
            throw new Exception("Failed to write JPEG to {$fullPath}");
        }
        imagedestroy($img);

        // 6. Build & return a relative path
        // If $directory is relative, just prepend it; otherwise strip off the DOCUMENT_ROOT
        if (DIRECTORY_SEPARATOR === '/' && strpos($directory, '/') === 0 && ! empty($_SERVER['DOCUMENT_ROOT'])) {
            $rel = str_replace(rtrim($_SERVER['DOCUMENT_ROOT'], '/'), '', $fullPath);
            return ltrim(str_replace('\\','/',$rel), '/');
        } else {
            return rtrim($directory, '/\\') . '/' . $filename;
        }
    }

    /**
     * Map a MIME type (e.g. “image/png”) to a file extension.
     *
     * @param  string  $mime
     * @return string
     */
    private function extensionFromMime(string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        return $map[$mime] ?? 'bin';
    }




}




class ai_functions
{

    static function checkCode($code, $apiKey) {

        $instructions = "You are a security code auditor. You are expert in javascript, jquery, php";

        $prompt = <<<EOT
        
        I will provide you with a code snippet. Your task is to analyze it and determine whether it is malicious or safe. 
        
        The code is:
        $code


        Specifically:

        Identify if the code attempts data exfiltration, injection, obfuscation, privilege escalation, or unauthorized access.

        Highlight any suspicious patterns (e.g., use of eval, document.write with external sources, hidden network requests, obfuscated variable names, crypto-mining code, etc.).
        
        Return json with 2 fields:
        Result: Safe / Unsafe
        Message: If it is safe the message will be empty, if it is Unsafe the message will contain explaination how it could be abused and suggestion with safer alternatives.

EOT;

            $ai = new ai($apiKey);
            $ai->instructions($instructions);
            $ai->prompt($prompt);
            $ai_response = $ai->send_request();
            $ai_content = $ai_response['content'];

            return json_decode($ai_content);


    }


}
