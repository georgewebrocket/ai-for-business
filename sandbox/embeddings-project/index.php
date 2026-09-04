<?php

declare(strict_types=1);

require __DIR__ . '/src/embeddings-lib.php';

$status = $_GET['status'] ?? '';
$output = $_GET['output'] ?? DEFAULT_OUTPUT;
$error = $_GET['error'] ?? '';
$inputFile = DEFAULT_INPUT;
$inputPath = projectFilePath($inputFile);
$products = [];
$previewTexts = [];
$pageError = '';

try {
    [, $products] = loadProductsFromFile($inputPath);
    $previewTexts = buildEmbeddingInputs($products);
} catch (Throwable $throwable) {
    $pageError = $throwable->getMessage();
}

$hasConfig = is_file(__DIR__ . '/config.php') || getenv('OPENAI_API_KEY');
$defaultModel = configuredModel();
?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Embeddings Demo</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
    <main class="shell">
        <header class="page-header">
            <div>
                <p class="eyebrow">OpenAI Embeddings API</p>
                <h1>Products Embeddings Demo</h1>
            </div>
            <div class="stats">
                <span><?= h(count($products)) ?></span>
                <small>προϊόντα</small>
            </div>
        </header>

        <?php if ($status === 'created'): ?>
            <section class="notice success">
                Τα embeddings δημιουργήθηκαν και γράφτηκαν στο <a href="<?= h($output) ?>" target="_blank"><?= h($output) ?></a>.
            </section>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <section class="notice error"><?= h($error) ?></section>
        <?php endif; ?>

        <?php if ($pageError !== ''): ?>
            <section class="notice error"><?= h($pageError) ?></section>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Ρυθμίσεις εκτέλεσης</h2>
                    <p>Το server-side PHP διαβάζει το JSON, καλεί το API και γράφει νέο JSON με πεδίο embeddings.</p>
                </div>
                <span class="config <?= $hasConfig ? 'ready' : 'missing' ?>"><?= $hasConfig ? 'API key έτοιμο' : 'λείπει API key' ?></span>
            </div>

            <form class="settings-form" method="post" action="generate.php">
                <label>
                    Input JSON
                    <input type="text" name="input_file" value="<?= h($inputFile) ?>" readonly>
                </label>
                <label>
                    Output JSON
                    <input type="text" name="output_file" value="<?= h(DEFAULT_OUTPUT) ?>">
                </label>
                <label>
                    Model
                    <input type="text" name="model" value="<?= h($defaultModel) ?>">
                </label>
                <button type="submit">Δημιουργία embeddings</button>
            </form>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Preview κειμένου</h2>
                    <p>Αυτά είναι τα κείμενα που θα σταλούν ως batch input στο Embeddings API.</p>
                </div>
            </div>

            <div class="product-list">
                <?php foreach ($previewTexts as $index => $text): ?>
                    <?php $product = $products[$index]; ?>
                    <article class="product-card">
                        <div class="product-title">
                            <strong><?= h($product['name'] ?? $product['productName'] ?? 'Προϊόν ' . ($index + 1)) ?></strong>
                            <?php if (isset($product['code'])): ?>
                                <span><?= h($product['code']) ?></span>
                            <?php endif; ?>
                        </div>
                        <pre><?= h($text) ?></pre>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</body>
</html>
