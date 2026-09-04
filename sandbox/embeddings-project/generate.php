<?php

declare(strict_types=1);

require __DIR__ . '/src/embeddings-lib.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: index.php');
        exit;
    }

    $inputFile = (string) ($_POST['input_file'] ?? DEFAULT_INPUT);
    $outputFile = (string) ($_POST['output_file'] ?? DEFAULT_OUTPUT);
    $model = trim((string) ($_POST['model'] ?? configuredModel()));

    if ($model === '') {
        throw new InvalidArgumentException('Model is required.');
    }

    $inputPath = projectFilePath($inputFile);
    $outputPath = projectFilePath($outputFile);
    $result = enrichProductsFile($inputPath, $outputPath, configuredApiKey(), $model);

    header('Location: index.php?status=created&output=' . rawurlencode(basename($result['output_path'])));
    exit;
} catch (Throwable $error) {
    header('Location: index.php?error=' . rawurlencode($error->getMessage()));
    exit;
}
