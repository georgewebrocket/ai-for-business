<?php

require_once(__DIR__ . '/../php/config.php');
require_once(__DIR__ . '/../php/db.php');
require_once(__DIR__ . '/../php/ai-settings.php');

date_default_timezone_set("Europe/Athens");

$db1 = new DB(conn1::$connstr, conn1::$username, conn1::$password);
$dbo = $db1;
$force = in_array('--force', $argv ?? [], true) || (isset($_GET['force']) && $_GET['force'] === '1');

function cron_ai_models_run_start($dbo) {
    $now = date('Y-m-d H:i:s');
    $id = $dbo->execSQL(
        'INSERT INTO cron_job_runs (job_name, status, started_at, created_at) VALUES (?, ?, ?, ?)',
        ['cron-refresh-ai-models', 'running', $now, $now]
    );
    return ['id' => (int)$id, 'started_at' => microtime(true)];
}

function cron_ai_models_run_finish($dbo, $run, $status, $message, $context = []) {
    if (empty($run['id'])) {
        return;
    }
    $dbo->execSQL(
        'UPDATE cron_job_runs
         SET account_id = ?, status = ?, finished_at = ?, duration_seconds = ?, message = ?
         WHERE id = ?',
        [
            isset($context['account_id']) ? (int)$context['account_id'] : null,
            $status,
            date('Y-m-d H:i:s'),
            round(microtime(true) - (float)$run['started_at'], 3),
            $message,
            (int)$run['id'],
        ]
    );
}

$run = cron_ai_models_run_start($dbo);
$accounts = $dbo->getRS("SELECT id, name FROM accounts WHERE status = 'active' ORDER BY id") ?: [];
$refreshed = 0;
$skipped = 0;
$failed = 0;
$messages = [];

foreach ($accounts as $account) {
    $accountId = (int)$account['id'];
    try {
        if (publisher_ai_api_key($dbo, $accountId) === '') {
            $skipped++;
            $messages[] = "Account {$accountId}: skipped - AI API key is not configured";
            continue;
        }

        $result = publisher_ai_refresh_model_registry($dbo, $accountId, $force);
        if (($result['status'] ?? '') === 'success') {
            $refreshed++;
            $registry = $result['registry'] ?? [];
            $messages[] = "Account {$accountId}: refreshed "
                . count($registry['text'] ?? []) . " text models, "
                . count($registry['image'] ?? []) . " image models";
        } else {
            $skipped++;
            $messages[] = "Account {$accountId}: skipped";
        }
    } catch (Throwable $ex) {
        $failed++;
        $messages[] = "Account {$accountId}: failed - " . $ex->getMessage();
    }
}

$status = $failed > 0 ? ($refreshed > 0 || $skipped > 0 ? 'success' : 'failed') : ($refreshed > 0 ? 'success' : 'skipped');
$summary = "AI model refresh: {$refreshed} refreshed, {$skipped} skipped, {$failed} failed";
if ($messages) {
    $summary .= "\n" . implode("\n", $messages);
}

cron_ai_models_run_finish($dbo, $run, $status, $summary);
echo $summary . "\n";
