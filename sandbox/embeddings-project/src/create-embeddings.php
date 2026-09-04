<?php

declare(strict_types=1);

require __DIR__ . '/embeddings-lib.php';

try {
    main($argv);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

function main(array $argv): void
{
    $options = parseArgs(array_slice($argv, 1));
    $inputPath = absolutePath($options['input'], projectRoot());
    $outputPath = absolutePath($options['output'], projectRoot());
    [, $products] = loadProductsFromFile($inputPath);
    $inputs = buildEmbeddingInputs($products);

    if ($options['dryRun']) {
        foreach ($inputs as $index => $text) {
            echo '--- Product ' . ($index + 1) . " ---\n";
            echo $text . "\n";
        }
        return;
    }

    $result = enrichProductsFile($inputPath, $outputPath, configuredApiKey(), $options['model']);
    echo 'Created embeddings for ' . $result['products_count'] . ' products with ' . $result['model'] . ".\n";
    echo 'Wrote ' . $result['output_path'] . "\n";
}

function parseArgs(array $args): array
{
    $options = [
        'input' => DEFAULT_INPUT,
        'output' => null,
        'model' => configuredModel(),
        'dryRun' => false,
        'inPlace' => false,
    ];

    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];

        if ($arg === '--input' || $arg === '-i') {
            $options['input'] = requireValue($args, ++$i, $arg);
        } elseif ($arg === '--output' || $arg === '-o') {
            $options['output'] = requireValue($args, ++$i, $arg);
        } elseif ($arg === '--model' || $arg === '-m') {
            $options['model'] = requireValue($args, ++$i, $arg);
        } elseif ($arg === '--dry-run') {
            $options['dryRun'] = true;
        } elseif ($arg === '--in-place') {
            $options['inPlace'] = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            printHelp();
            exit(0);
        } else {
            throw new InvalidArgumentException("Unknown argument: {$arg}");
        }
    }

    if ($options['inPlace']) {
        $options['output'] = $options['input'];
    }

    if (!$options['output']) {
        $pathInfo = pathinfo($options['input']);
        $dir = isset($pathInfo['dirname']) && $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] . DIRECTORY_SEPARATOR : '';
        $extension = isset($pathInfo['extension']) && $pathInfo['extension'] !== '' ? '.' . $pathInfo['extension'] : '.json';
        $options['output'] = $dir . $pathInfo['filename'] . '.with-embeddings' . $extension;
    }

    return $options;
}

function requireValue(array $args, int $index, string $option): string
{
    if (!isset($args[$index]) || str_starts_with($args[$index], '-')) {
        throw new InvalidArgumentException("Missing value for {$option}");
    }

    return $args[$index];
}

function printHelp(): void
{
    echo 'Usage:
  php src/create-embeddings.php --input products.json

Options:
  -i, --input <file>   JSON file to read. Default: ' . DEFAULT_INPUT . '
  -o, --output <file>  JSON file to write. Default: <input>.with-embeddings.json
  -m, --model <model>  Embeddings model. Default: ' . DEFAULT_MODEL . '
  --in-place           Update the input JSON file directly
  --dry-run            Print the text that would be embedded without calling the API
  -h, --help           Show this help
';
}
