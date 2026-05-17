<?php

function publisher_ai_text_model_options() {
    return [
        'gpt-5.2' => 'GPT-5.2',
        'gpt-5.2-chat-latest' => 'GPT-5.2 Chat Latest',
        'gpt-5.2-pro' => 'GPT-5.2 Pro',
        'gpt-5.1' => 'GPT-5.1',
        'gpt-4.1' => 'GPT-4.1',
    ];
}

function publisher_ai_image_model_options() {
    return [
        'gpt-image-1.5' => 'GPT Image 1.5',
        'gpt-image-1' => 'GPT Image 1',
        'gpt-image-1-mini' => 'GPT Image 1 Mini',
        'chatgpt-image-latest' => 'ChatGPT Image Latest',
    ];
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

function publisher_ai_normalize_text_model($model, $default = 'gpt-5.2') {
    $model = trim((string)$model);
    $options = publisher_ai_text_model_options();
    return isset($options[$model]) ? $model : $default;
}

function publisher_ai_normalize_image_model($model, $default = 'gpt-image-1.5') {
    $model = trim((string)$model);
    $options = publisher_ai_image_model_options();
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

function publisher_property_ai_defaults($settings) {
    $ai = publisher_ai_get_settings_section(is_array($settings) ? $settings : [], 'ai');
    return [
        'text_model' => publisher_ai_normalize_text_model($ai['default_text_model'] ?? $ai['text_model'] ?? 'gpt-5.2'),
        'image_model' => publisher_ai_normalize_image_model($ai['default_image_model'] ?? $ai['image_model'] ?? 'gpt-image-1.5'),
    ];
}

function publisher_stage_ai_settings($settings, $sectionKey, $defaults) {
    $section = publisher_ai_get_settings_section(is_array($settings) ? $settings : [], $sectionKey);
    return [
        'text_model' => publisher_ai_normalize_text_model($section['text_model'] ?? ($defaults['text_model'] ?? 'gpt-5.2'), $defaults['text_model'] ?? 'gpt-5.2'),
        'image_model' => publisher_ai_normalize_image_model($section['image_model'] ?? ($defaults['image_model'] ?? 'gpt-image-1.5'), $defaults['image_model'] ?? 'gpt-image-1.5'),
    ];
}

function publisher_idea_ai_settings($idea, $defaults) {
    $metadata = json_decode((string)($idea['ai_response_json'] ?? ''), true);
    $stored = is_array($metadata) ? ($metadata['ai_models'] ?? []) : [];
    return [
        'text_model' => publisher_ai_normalize_text_model($stored['content_text_model'] ?? $stored['text_model'] ?? ($defaults['text_model'] ?? 'gpt-5.2'), $defaults['text_model'] ?? 'gpt-5.2'),
        'image_model' => publisher_ai_normalize_image_model($stored['content_image_model'] ?? $stored['image_model'] ?? ($defaults['image_model'] ?? 'gpt-image-1.5'), $defaults['image_model'] ?? 'gpt-image-1.5'),
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
