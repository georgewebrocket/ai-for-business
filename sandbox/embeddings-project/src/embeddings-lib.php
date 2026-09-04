<?php

declare(strict_types=1);

const DEFAULT_MODEL = 'text-embedding-3-small';
const DEFAULT_INPUT = 'products.json';
const DEFAULT_OUTPUT = 'products.with-embeddings.json';
const EMBEDDINGS_ENDPOINT = 'https://api.openai.com/v1/embeddings';

function projectRoot(): string
{
    return dirname(__DIR__);
}

function fieldAliases(): array
{
    return [
        'productName' => ['productName', 'name', 'title', 'onoma', 'onomasia', 'ονομασια', 'όνομα', 'ονομα'],
        'description' => ['description', 'desc', 'perigrafi', 'περιγραφή', 'περιγραφη'],
        'uses' => ['uses', 'use', 'usage', 'xriseis', 'χρήσεις', 'χρησεις'],
        'material' => ['material', 'materials', 'yliko', 'υλικό', 'υλικο'],
        'dimensions' => ['dimensions', 'dimension', 'size', 'diastaseis', 'διαστάσεις', 'διαστασεις'],
    ];
}

function loadLocalConfig(): array
{
    $configPath = projectRoot() . DIRECTORY_SEPARATOR . 'config.php';

    if (!is_file($configPath)) {
        return [];
    }

    $config = require $configPath;

    if (!is_array($config)) {
        throw new RuntimeException('config.php must return an array.');
    }

    return $config;
}

function configuredModel(): string
{
    $config = loadLocalConfig();
    return (string) ($config['OPENAI_EMBEDDING_MODEL'] ?? getenv('OPENAI_EMBEDDING_MODEL') ?: DEFAULT_MODEL);
}

function configuredApiKey(): string
{
    $config = loadLocalConfig();
    $apiKey = (string) ($config['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '');

    if ($apiKey === '') {
        throw new RuntimeException('Missing OPENAI_API_KEY. Set it in the server environment or create config.php from config.example.php.');
    }

    return $apiKey;
}

function absolutePath(string $path, ?string $basePath = null): string
{
    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
        return $path;
    }

    return ($basePath ?? getcwd()) . DIRECTORY_SEPARATOR . $path;
}

function projectFilePath(string $fileName): string
{
    if (!preg_match('/^[A-Za-z0-9._-]+\.json$/', $fileName)) {
        throw new InvalidArgumentException('Only JSON file names in the project folder are allowed.');
    }

    return projectRoot() . DIRECTORY_SEPARATOR . $fileName;
}

function readJsonFile(string $path): mixed
{
    if (!is_file($path)) {
        throw new RuntimeException("Input file not found: {$path}");
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Could not read input file: {$path}");
    }

    $json = json_decode($contents, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Invalid JSON: ' . json_last_error_msg());
    }

    return $json;
}

function writeJsonFile(string $path, mixed $json): void
{
    $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Could not encode output JSON: ' . json_last_error_msg());
    }

    if (file_put_contents($path, $encoded . PHP_EOL) === false) {
        throw new RuntimeException("Could not write output file: {$path}");
    }
}

function getProductsContainer(mixed $json): array
{
    if (is_array($json) && array_is_list($json)) {
        return [
            $json,
            fn (array $products): array => $products,
        ];
    }

    if (is_array($json) && isset($json['products']) && is_array($json['products'])) {
        return [
            $json['products'],
            function (array $products) use ($json): array {
                $json['products'] = $products;
                return $json;
            },
        ];
    }

    throw new RuntimeException('Input JSON must be an array of products or an object with a products array.');
}

function loadProductsFromFile(string $path): array
{
    $json = readJsonFile($path);
    [$products, $writeBack] = getProductsContainer($json);

    return [$json, $products, $writeBack];
}

function getFieldValue(array $product, string $fieldName, array $fieldAliases): string
{
    foreach ($fieldAliases[$fieldName] as $alias) {
        if (array_key_exists($alias, $product)) {
            $value = formatProductValue($product[$alias]);

            if ($value !== '') {
                return $value;
            }
        }
    }

    return '';
}

function formatProductValue(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_scalar($value)) {
        return trim((string) $value);
    }

    if (is_array($value) && array_is_list($value)) {
        $items = array_filter(
            array_map(fn (mixed $item): string => formatProductValue($item), $value),
            fn (string $item): bool => $item !== ''
        );

        return implode(', ', $items);
    }

    if (is_array($value)) {
        $items = [];

        foreach ($value as $key => $item) {
            $formatted = formatProductValue($item);

            if ($formatted !== '') {
                $items[] = "{$key}: {$formatted}";
            }
        }

        return implode(', ', $items);
    }

    return '';
}

function productToEmbeddingText(array $product, ?array $aliases = null): string
{
    $fieldAliases = $aliases ?? fieldAliases();
    $parts = [
        ['Ονομασία προϊόντος', getFieldValue($product, 'productName', $fieldAliases)],
        ['Περιγραφή', getFieldValue($product, 'description', $fieldAliases)],
        ['Χρήσεις', getFieldValue($product, 'uses', $fieldAliases)],
        ['Υλικό', getFieldValue($product, 'material', $fieldAliases)],
        ['Διαστάσεις', getFieldValue($product, 'dimensions', $fieldAliases)],
    ];

    $lines = [];
    foreach ($parts as [$label, $value]) {
        if ($value !== '') {
            $lines[] = "{$label}: {$value}";
        }
    }

    return implode("\n", $lines);
}

function buildEmbeddingInputs(array $products): array
{
    $inputs = array_map(
        fn (array $product): string => productToEmbeddingText($product),
        $products
    );

    $emptyRows = [];
    foreach ($inputs as $index => $text) {
        if ($text === '') {
            $emptyRows[] = $index;
        }
    }

    if ($emptyRows !== []) {
        throw new RuntimeException('Products with no embeddable text: ' . implode(', ', $emptyRows));
    }

    return $inputs;
}

function enrichProductsFile(string $inputPath, string $outputPath, string $apiKey, string $model): array
{
    [, $products, $writeBack] = loadProductsFromFile($inputPath);
    $inputs = buildEmbeddingInputs($products);
    $embeddings = createEmbeddings($apiKey, $model, $inputs);
    $enrichedProducts = [];

    foreach ($products as $index => $product) {
        $product['embeddings'] = $embeddings[$index];
        $enrichedProducts[] = $product;
    }

    writeJsonFile($outputPath, $writeBack($enrichedProducts));

    return [
        'products_count' => count($products),
        'output_path' => $outputPath,
        'model' => $model,
    ];
}

function createEmbeddings(string $apiKey, string $model, array $inputs): array
{
    $payload = requestOpenAiEmbeddings($apiKey, [
        'model' => $model,
        'input' => $inputs,
        'encoding_format' => 'float',
    ]);

    if (!isset($payload['data']) || !is_array($payload['data'])) {
        throw new RuntimeException('OpenAI API response did not contain a data array.');
    }

    usort($payload['data'], fn (array $a, array $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

    return array_map(function (array $item): array {
        if (!isset($item['embedding']) || !is_array($item['embedding'])) {
            throw new RuntimeException('OpenAI API response item did not contain an embedding array.');
        }

        return $item['embedding'];
    }, $payload['data']);
}

function requestOpenAiEmbeddings(string $apiKey, array $payload): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        throw new RuntimeException('Could not encode API request JSON: ' . json_last_error_msg());
    }

    if (function_exists('curl_init')) {
        return requestWithCurl($apiKey, $body);
    }

    return requestWithStreamContext($apiKey, $body);
}

function requestWithCurl(string $apiKey, string $body): array
{
    $curl = curl_init(EMBEDDINGS_ENDPOINT);
    if ($curl === false) {
        throw new RuntimeException('Could not initialize cURL.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $body,
    ]);

    $responseBody = curl_exec($curl);
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($responseBody === false) {
        throw new RuntimeException("OpenAI API request failed: {$error}");
    }

    return decodeApiResponse((string) $responseBody, (int) $statusCode);
}

function requestWithStreamContext(string $apiKey, string $body): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body),
            ]),
            'content' => $body,
            'ignore_errors' => true,
        ],
    ]);

    $responseBody = file_get_contents(EMBEDDINGS_ENDPOINT, false, $context);
    $statusCode = 0;

    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
        $statusCode = (int) $matches[1];
    }

    if ($responseBody === false) {
        throw new RuntimeException('OpenAI API request failed.');
    }

    return decodeApiResponse($responseBody, $statusCode);
}

function decodeApiResponse(string $responseBody, int $statusCode): array
{
    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException("OpenAI API error {$statusCode}: {$responseBody}");
    }

    $json = json_decode($responseBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Could not parse OpenAI API response: ' . json_last_error_msg());
    }

    return $json;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
