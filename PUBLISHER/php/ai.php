<?php


class ai
{

    protected $apiKey, $instructions, $prompt, $lang;

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

    public function send_request() {
        // Prepare request
        $data = [
            "model" => "gpt-4o",
            "messages" => [
                ["role" => "system", "content" => $this->instructions ],
                ["role" => "user", "content" => $this->prompt]
            ],
            "temperature" => 0.7,
            "max_tokens"        => 4000,
            "top_p"             => 1,
            "frequency_penalty" => 0,
            "presence_penalty"  => 0,
        ];

        // Initialize cURL
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Execute request
        $response = curl_exec($ch);


        // 1. Transport / cURL error?
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            $err_msg = json_encode(['error' => 'cURL error: ' . $err]);
            return [
                'result' => 'error',
                'message' => $err_msg,
            ];
        }

        // 2. HTTP status code check
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            // Try to decode any error message from the body
            $body = json_decode($response, true);
            $message = $body['error']['message'] 
                    ?? 'Unexpected HTTP status: ' . $httpCode;
            $err_msg = json_encode([
                'error' => 'API request failed',
                'status' => $httpCode,
                'message' => $message
            ]);
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
            return [
                'result' => 'error',
                'message' => $err_msg,
            ];
        }

        // 5. Extract content safely
        $content = $result['choices'][0]['message']['content'] ?? '';
        if ($content === '') {
            $err_msg = json_encode(['error' => 'Empty content in API response']);
            return [
                'result' => 'error',
                'message' => $err_msg,
            ];
        }

        // Decode the response
        $result = json_decode($response, true);
        $content = $result['choices'][0]['message']['content'] ?? '';

        // Parse the JSON from the AI output
        // Remove ```json and ``` if present
        $cleaned = trim($content);
        $cleaned = preg_replace('/^```json\s*/', '', $cleaned); // Remove starting ```json
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);     // Remove ending ```

        $ai_content = $cleaned;

        $promptTokens = $result['usage']['prompt_tokens'] ?? 0;
        $completionTokens = $result['usage']['completion_tokens'] ?? 0;
        // Υπολογισμός κόστους (gpt-4o)
        $promptCost = ($promptTokens / 1000) * 0.005;
        $completionCost = ($completionTokens / 1000) * 0.015;
        $totalCost = $promptCost + $completionCost;
        $ai_cost = "<p>Εκτιμώμενο Κόστος GPT-4o: $totalCost $</p>";

        $response = [
            "result" => 'success',
            "content" => $ai_content,
            "cost" => $ai_cost
        ];
        return $response;

    }


    private function generate_openai_image() {
        $endpoint = 'https://api.openai.com/v1/images/generations';
        $data = [
            "model" => "dall-e-3",
            'prompt' => $this->prompt,
            'n'      => 1,
            'size'   => '1792x1024'
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
            throw new Exception('OpenAI API request error: ' . curl_error($ch));
        }
        curl_close($ch);

        $body = json_decode($response, true);
        if (!isset($body['data'][0]['url'])) {
            throw new Exception('Unexpected OpenAI response: ' . $response);
        }

        // $usage = $body['usage'];
        // echo "Prompt tokens: {$usage['prompt_tokens']}<br/>";
        // echo "Completion tokens: {$usage['completion_tokens']}<br/>";
        // echo "Total tokens: {$usage['total_tokens']}<br/>";

        return $body['data'][0]['url'];

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
        $url       = $this->generate_openai_image();
        $imageData = $this->download_image_data($url);

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