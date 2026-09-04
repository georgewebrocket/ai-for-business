<?php

function publisher_ai_static_text_model_options() {
    return [
        'gpt-5.5' => 'GPT-5.5',
        'gpt-5.4' => 'GPT-5.4',
        'gpt-5.4-mini' => 'GPT-5.4 Mini',
        'gpt-5.4-nano' => 'GPT-5.4 Nano',
        'gpt-5.2' => 'GPT-5.2',
        'gpt-5.2-chat-latest' => 'GPT-5.2 Chat Latest',
        'gpt-5.2-pro' => 'GPT-5.2 Pro',
        'gpt-5.1' => 'GPT-5.1',
        'gpt-4.1' => 'GPT-4.1',
    ];
}

function publisher_ai_static_image_model_options() {
    return [
        'gpt-image-2' => 'GPT Image 2',
        'gpt-image-1.5' => 'GPT Image 1.5',
        'gpt-image-1' => 'GPT Image 1',
        'gpt-image-1-mini' => 'GPT Image 1 Mini',
        'chatgpt-image-latest' => 'ChatGPT Image Latest',
    ];
}

function publisher_ai_model_registry_key() {
    return 'ai-model-registry-json';
}

function publisher_ai_model_label($modelId) {
    $label = str_replace(['-', '_'], ' ', trim((string)$modelId));
    $label = preg_replace('/\s+/', ' ', $label);
    return ucwords($label);
}

function publisher_ai_model_registry($dbo, $accountId) {
    $accountId = (int)$accountId;
    if (!$dbo || $accountId <= 0) {
        return [];
    }

    $rows = $dbo->getRS(
        'SELECT key_value
         FROM settings
         WHERE account_id = ? AND key_code = ?
         ORDER BY id DESC
         LIMIT 1',
        [$accountId, publisher_ai_model_registry_key()]
    );
    if (!$rows) {
        return [];
    }

    $registry = json_decode((string)$rows[0]['key_value'], true);
    return is_array($registry) ? $registry : [];
}

function publisher_ai_model_registry_options($dbo, $accountId, $type) {
    $registry = publisher_ai_model_registry($dbo, $accountId);
    $models = isset($registry[$type]) && is_array($registry[$type]) ? $registry[$type] : [];
    $options = [];
    foreach ($models as $model) {
        $id = is_array($model) ? trim((string)($model['id'] ?? '')) : trim((string)$model);
        if ($id === '') {
            continue;
        }
        $options[$id] = is_array($model) && trim((string)($model['label'] ?? '')) !== ''
            ? trim((string)$model['label'])
            : publisher_ai_model_label($id);
    }
    return $options;
}

function publisher_ai_text_model_options($dbo = null, $accountId = null) {
    return publisher_ai_merge_model_options(
        publisher_ai_static_text_model_options(),
        publisher_ai_model_registry_options($dbo, $accountId, 'text')
    );
}

function publisher_ai_image_model_options($dbo = null, $accountId = null) {
    return publisher_ai_merge_model_options(
        publisher_ai_static_image_model_options(),
        publisher_ai_model_registry_options($dbo, $accountId, 'image')
    );
}

function publisher_ai_merge_model_options($fallbackOptions, $dynamicOptions) {
    $fallbackOptions = is_array($fallbackOptions) ? $fallbackOptions : [];
    $dynamicOptions = is_array($dynamicOptions) ? $dynamicOptions : [];
    return $dynamicOptions + $fallbackOptions;
}

function publisher_ai_registry_is_stale($registry, $maxAgeSeconds = 604800) {
    $checkedAt = is_array($registry) ? strtotime((string)($registry['last_checked_at'] ?? '')) : false;
    if (!$checkedAt) {
        return true;
    }
    return (time() - $checkedAt) >= (int)$maxAgeSeconds;
}

function publisher_ai_model_is_text_generation($modelId) {
    $modelId = strtolower(trim((string)$modelId));
    if ($modelId === '') {
        return false;
    }
    foreach (['embedding', 'audio', 'tts', 'whisper', 'transcribe', 'moderation', 'image', 'dall-e', 'realtime', 'search'] as $blocked) {
        if (strpos($modelId, $blocked) !== false) {
            return false;
        }
    }
    return preg_match('/^(gpt|o[0-9]|o-|chatgpt)/', $modelId) === 1;
}

function publisher_ai_model_is_image_generation($modelId) {
    $modelId = strtolower(trim((string)$modelId));
    if ($modelId === '') {
        return false;
    }
    return strpos($modelId, 'gpt-image') === 0
        || strpos($modelId, 'chatgpt-image') === 0;
}

function publisher_ai_fetch_openai_models($apiKey) {
    $apiKey = trim((string)$apiKey);
    if ($apiKey === '') {
        throw new Exception('AI API key is not configured.');
    }

    $ch = curl_init('https://api.openai.com/v1/models');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $message = curl_error($ch);
        curl_close($ch);
        throw new Exception('OpenAI models request error: ' . $message);
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $message = is_array($body) ? ($body['error']['message'] ?? 'Unexpected HTTP status: ' . $httpCode) : ('Unexpected HTTP status: ' . $httpCode);
        throw new Exception('OpenAI models request failed: ' . $message);
    }
    if (!is_array($body) || !isset($body['data']) || !is_array($body['data'])) {
        throw new Exception('OpenAI models response was not valid JSON.');
    }

    $models = $body['data'];
    usort($models, function($a, $b) {
        return (int)($b['created'] ?? 0) <=> (int)($a['created'] ?? 0);
    });

    return $models;
}

function publisher_ai_refresh_model_registry($dbo, $accountId, $force = false) {
    $accountId = (int)$accountId;
    if ($accountId <= 0) {
        throw new Exception('Account id is required.');
    }

    $existing = publisher_ai_model_registry($dbo, $accountId);
    if (!$force && !publisher_ai_registry_is_stale($existing)) {
        return ['status' => 'skipped', 'registry' => $existing, 'message' => 'AI model registry is still fresh.'];
    }

    $models = publisher_ai_fetch_openai_models(publisher_require_ai_api_key($dbo, $accountId));
    $registry = [
        'provider' => 'openai',
        'last_checked_at' => date('Y-m-d H:i:s'),
        'text' => [],
        'image' => [],
    ];

    foreach ($models as $model) {
        $id = trim((string)($model['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $entry = [
            'id' => $id,
            'label' => publisher_ai_model_label($id),
            'created' => isset($model['created']) ? (int)$model['created'] : null,
        ];
        if (publisher_ai_model_is_text_generation($id)) {
            $registry['text'][] = $entry;
        }
        if (publisher_ai_model_is_image_generation($id)) {
            $registry['image'][] = $entry;
        }
    }

    publisher_ai_save_model_registry($dbo, $accountId, $registry);
    return ['status' => 'success', 'registry' => $registry, 'message' => 'AI model registry refreshed.'];
}

function publisher_ai_save_model_registry($dbo, $accountId, $registry) {
    $accountId = (int)$accountId;
    $key = publisher_ai_model_registry_key();
    $json = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $now = date('YmdHis');
    $rows = $dbo->getRS(
        'SELECT id FROM settings WHERE account_id = ? AND key_code = ? ORDER BY id DESC LIMIT 1',
        [$accountId, $key]
    );

    if ($rows) {
        return $dbo->execSQL(
            'UPDATE settings SET title = ?, key_value = ?, date_modified = ? WHERE id = ? AND account_id = ?',
            ['AI Model Registry', $json, $now, (int)$rows[0]['id'], $accountId]
        );
    }

    return $dbo->execSQL(
        'INSERT INTO settings (account_id, key_code, title, key_value, s_type, date_modified)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$accountId, $key, 'AI Model Registry', $json, 1, $now]
    );
}

function publisher_ai_api_key($dbo, $accountId) {
    $accountId = (int)$accountId;
    if ($accountId <= 0) {
        return '';
    }

    $rows = $dbo->getRS(
        'SELECT key_value
         FROM settings
         WHERE account_id = ? AND key_code = ?
         ORDER BY id DESC
         LIMIT 1',
        [$accountId, 'ai-api-key']
    );

    return $rows ? trim((string)$rows[0]['key_value']) : '';
}

function publisher_require_ai_api_key($dbo, $accountId) {
    $apiKey = publisher_ai_api_key($dbo, $accountId);
    if ($apiKey === '') {
        throw new Exception('AI API key is not configured for this account. Add a setting with key_code "ai-api-key".');
    }
    return $apiKey;
}

function publisher_ai_normalize_text_model($model, $default = 'gpt-5.5', $dbo = null, $accountId = null) {
    $model = trim((string)$model);
    $options = publisher_ai_text_model_options($dbo, $accountId);
    return isset($options[$model]) ? $model : $default;
}

function publisher_ai_normalize_image_model($model, $default = 'gpt-image-2', $dbo = null, $accountId = null) {
    $model = trim((string)$model);
    $options = publisher_ai_image_model_options($dbo, $accountId);
    return isset($options[$model]) ? $model : $default;
}

function publisher_ai_get_settings_section($settings, $sectionKey) {
    if (isset($settings[$sectionKey]) && is_array($settings[$sectionKey])) {
        return $settings[$sectionKey];
    }
    if (empty($settings['sections']) || !is_array($settings['sections'])) {
        return [];
    }
    foreach ($settings['sections'] as $section) {
        if (($section['key'] ?? '') !== $sectionKey || empty($section['options']) || !is_array($section['options'])) {
            continue;
        }
        $values = [];
        foreach ($section['options'] as $option) {
            if (isset($option['key'])) {
                $values[$option['key']] = $option['value'] ?? null;
            }
        }
        return $values;
    }
    return [];
}

function publisher_ai_compact_setting_value($value) {
    if (is_array($value)) {
        $compacted = [];
        foreach ($value as $key => $item) {
            $item = publisher_ai_compact_setting_value($item);
            if ($item !== null && $item !== '' && $item !== []) {
                $compacted[$key] = $item;
            }
        }
        return $compacted;
    }

    if (is_bool($value) || is_int($value) || is_float($value)) {
        return $value;
    }

    return trim((string)$value);
}

function publisher_property_general_settings($settings) {
    $settings = is_array($settings) ? $settings : [];
    $context = [];
    foreach (['branding', 'ai', 'seo'] as $sectionKey) {
        $section = publisher_ai_get_settings_section($settings, $sectionKey);
        $section = publisher_ai_compact_setting_value($section);
        if (is_array($section) && $section) {
            $context[$sectionKey] = $section;
        }
    }
    return $context;
}

function publisher_property_general_settings_prompt_block($settings) {
    $context = publisher_property_general_settings($settings);
    if (!$context) {
        return '';
    }

    $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return <<<PROMPT
Property general settings (Branding, AI, SEO):
Use these settings as property-level guidance. More specific content mix, writing style, template, brief, section, and safety instructions take precedence when they conflict.
{$json}
PROMPT;
}

function publisher_property_general_settings_prompt_block_from_db($dbo, $accountId, $propertyId) {
    $rows = $dbo->getRS(
        'SELECT settings_json FROM properties WHERE id = ? AND account_id = ? LIMIT 1',
        [(int)$propertyId, (int)$accountId]
    );
    if (!$rows) {
        return '';
    }

    $settings = json_decode((string)$rows[0]['settings_json'], true);
    return publisher_property_general_settings_prompt_block(is_array($settings) ? $settings : []);
}

function publisher_ai_set_settings_section($settings, $sectionKey, $title, $values) {
    $settings = is_array($settings) ? $settings : [];
    if (empty($settings['sections']) || !is_array($settings['sections'])) {
        $settings = ['sections' => []];
    }

    $section = [
        'title' => $title,
        'key' => $sectionKey,
        'options' => [],
    ];
    foreach ($values as $key => $value) {
        $section['options'][] = [
            'title' => ucwords(str_replace('_', ' ', (string)$key)),
            'key' => (string)$key,
            'value' => $value,
        ];
    }

    foreach ($settings['sections'] as $index => $existing) {
        if (($existing['key'] ?? '') === $sectionKey) {
            $settings['sections'][$index] = $section;
            return $settings;
        }
    }
    $settings['sections'][] = $section;
    return $settings;
}

function publisher_property_ai_defaults($settings, $dbo = null, $accountId = null) {
    $ai = publisher_ai_get_settings_section(is_array($settings) ? $settings : [], 'ai');
    return [
        'text_model' => publisher_ai_normalize_text_model($ai['default_text_model'] ?? $ai['text_model'] ?? 'gpt-5.5', 'gpt-5.5', $dbo, $accountId),
        'image_model' => publisher_ai_normalize_image_model($ai['default_image_model'] ?? $ai['image_model'] ?? 'gpt-image-2', 'gpt-image-2', $dbo, $accountId),
    ];
}

function publisher_stage_ai_settings($settings, $sectionKey, $defaults, $dbo = null, $accountId = null) {
    $section = publisher_ai_get_settings_section(is_array($settings) ? $settings : [], $sectionKey);
    return [
        'text_model' => publisher_ai_normalize_text_model($section['text_model'] ?? ($defaults['text_model'] ?? 'gpt-5.5'), $defaults['text_model'] ?? 'gpt-5.5', $dbo, $accountId),
        'image_model' => publisher_ai_normalize_image_model($section['image_model'] ?? ($defaults['image_model'] ?? 'gpt-image-2'), $defaults['image_model'] ?? 'gpt-image-2', $dbo, $accountId),
    ];
}

function publisher_idea_ai_settings($idea, $defaults, $dbo = null, $accountId = null) {
    $metadata = json_decode((string)($idea['ai_response_json'] ?? ''), true);
    $stored = is_array($metadata) ? ($metadata['ai_models'] ?? []) : [];
    return [
        'text_model' => publisher_ai_normalize_text_model($stored['content_text_model'] ?? $stored['text_model'] ?? ($defaults['text_model'] ?? 'gpt-5.5'), $defaults['text_model'] ?? 'gpt-5.5', $dbo, $accountId),
        'image_model' => publisher_ai_normalize_image_model($stored['content_image_model'] ?? $stored['image_model'] ?? ($defaults['image_model'] ?? 'gpt-image-2'), $defaults['image_model'] ?? 'gpt-image-2', $dbo, $accountId),
    ];
}

function publisher_merge_idea_ai_metadata($json, $aiModels) {
    $metadata = json_decode((string)$json, true);
    if (!is_array($metadata)) {
        $metadata = [];
    }
    $existing = isset($metadata['ai_models']) && is_array($metadata['ai_models']) ? $metadata['ai_models'] : [];
    $metadata['ai_models'] = array_merge($existing, [
        'text_model' => publisher_ai_normalize_text_model($aiModels['text_model'] ?? null),
        'image_model' => publisher_ai_normalize_image_model($aiModels['image_model'] ?? null),
        'content_text_model' => publisher_ai_normalize_text_model($aiModels['content_text_model'] ?? ($aiModels['text_model'] ?? null)),
        'content_image_model' => publisher_ai_normalize_image_model($aiModels['content_image_model'] ?? ($aiModels['image_model'] ?? null)),
    ]);
    return json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
