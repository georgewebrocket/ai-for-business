<?php

function publisher_json_decode_array($json) {
    $decoded = json_decode((string)$json, true);
    return is_array($decoded) ? $decoded : [];
}

function publisher_channel_credentials($channel) {
    return publisher_json_decode_array($channel['credentials_json'] ?? '');
}

function publisher_channel_settings($channel) {
    return publisher_json_decode_array($channel['settings_json'] ?? '');
}

function publisher_normalize_base_url($url) {
    $url = trim((string)$url);
    $url = str_replace('\\/', '/', $url);
    return rtrim($url, '/');
}

function publisher_limit_text($text, $maxLength) {
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $maxLength) {
        return rtrim(mb_substr($text, 0, $maxLength, 'UTF-8'));
    }
    if (!function_exists('mb_strlen') && strlen($text) > $maxLength) {
        return rtrim(substr($text, 0, $maxLength));
    }
    return $text;
}

function publisher_media_web_url($asset) {
    $url = trim((string)($asset['file_path'] ?: $asset['external_url']));
    if ($url === '') {
        return '';
    }
    $url = str_replace('\\', '/', $url);
    if (stripos($url, 'http') === 0) {
        return $url;
    }
    $marker = '/PUBLISHER/';
    $pos = strpos($url, $marker);
    if ($pos !== false) {
        return substr($url, $pos + strlen($marker));
    }
    return ltrim($url, '/');
}

function publisher_media_local_path($asset) {
    $url = publisher_media_web_url($asset);
    if ($url === '' || stripos($url, 'http') === 0) {
        return '';
    }
    return realpath(__DIR__ . '/../' . $url) ?: '';
}

function publisher_first_media_asset($dbo, $contentItemId, $accountId, $propertyId) {
    $rows = $dbo->getRS(
        'SELECT * FROM media_assets WHERE content_item_id = ? AND account_id = ? AND property_id = ? AND COALESCE(file_path, external_url, "") <> "" ORDER BY created_at DESC, id DESC LIMIT 1',
        [$contentItemId, $accountId, $propertyId]
    );
    return $rows ? $rows[0] : null;
}

function publisher_content_item_categories($dbo, $contentItemId, $accountId, $propertyId) {
    $rows = $dbo->getRS(
        'SELECT cc.id, cc.name, cc.slug
         FROM content_item_categories cic
         INNER JOIN content_categories cc ON cc.id = cic.category_id
         WHERE cic.content_item_id = ? AND cc.account_id = ? AND cc.property_id = ? AND cc.status = ?
         ORDER BY cc.name',
        [$contentItemId, $accountId, $propertyId, 'active']
    ) ?: [];

    return array_values(array_filter($rows, function($row) {
        return trim((string)($row['slug'] ?? '')) !== '';
    }));
}

function publisher_latest_external_url($dbo, $contentItemId, $accountId, $propertyId) {
    $rows = $dbo->getRS(
        'SELECT external_url FROM content_publications
         WHERE content_item_id = ? AND account_id = ? AND property_id = ? AND status = ? AND COALESCE(external_url, "") <> ""
         ORDER BY published_at DESC, id DESC LIMIT 1',
        [$contentItemId, $accountId, $propertyId, 'published']
    );
    return $rows ? (string)$rows[0]['external_url'] : '';
}

function publisher_http_json($method, $url, $headers, $payload) {
    $ch = curl_init($url);
    $headers[] = 'Content-Type: application/json';
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'AI Publisher/1.0',
    ];
    if (defined('CURL_IPRESOLVE_V4')) {
        $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }
    curl_setopt_array($ch, $curlOptions);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception($error);
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $message = is_array($decoded) ? ($decoded['message'] ?? $decoded['error']['message'] ?? $response) : $response;
        throw new Exception($message);
    }
    return is_array($decoded) ? $decoded : ['raw' => $response];
}

function publisher_http_get_json($url, $headers) {
    $ch = curl_init($url);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'AI Publisher/1.0',
    ];
    if (defined('CURL_IPRESOLVE_V4')) {
        $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }
    curl_setopt_array($ch, $curlOptions);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception($error);
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $message = is_array($decoded) ? ($decoded['message'] ?? $decoded['error']['message'] ?? $response) : $response;
        throw new Exception($message);
    }
    return is_array($decoded) ? $decoded : ['raw' => $response];
}

function publisher_wordpress_category_ids_by_slug($siteUrl, $auth, $categories) {
    $ids = [];
    foreach ($categories as $category) {
        $slug = trim((string)($category['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $matches = publisher_http_get_json(
            $siteUrl . '/wp-json/wp/v2/categories?slug=' . rawurlencode($slug) . '&per_page=1',
            [$auth]
        );
        if (!$matches || !isset($matches[0]['id'])) {
            throw new Exception('WordPress category with slug "' . $slug . '" was not found.');
        }
        $ids[] = (int)$matches[0]['id'];
    }

    return array_values(array_unique($ids));
}

function publisher_wordpress_seo_meta($item, $settings) {
    $metaTitle = publisher_limit_text($item['meta_title'] ?? ($item['title'] ?? ''), 60);
    $metaDescription = publisher_limit_text($item['meta_description'] ?? ($item['summary'] ?? ''), 160);
    if ($metaTitle === '' && $metaDescription === '') {
        return [];
    }

    $meta = [];
    $titleKeys = $settings['seo_meta_title_keys'] ?? ['_yoast_wpseo_title', 'rank_math_title'];
    $descriptionKeys = $settings['seo_meta_description_keys'] ?? ['_yoast_wpseo_metadesc', 'rank_math_description'];

    foreach ((array)$titleKeys as $key) {
        $key = trim((string)$key);
        if ($key !== '' && $metaTitle !== '') {
            $meta[$key] = $metaTitle;
        }
    }
    foreach ((array)$descriptionKeys as $key) {
        $key = trim((string)$key);
        if ($key !== '' && $metaDescription !== '') {
            $meta[$key] = $metaDescription;
        }
    }

    return $meta;
}

function publisher_http_upload($url, $headers, $body, $contentType, $filename) {
    $ch = curl_init($url);
    $headers[] = 'Content-Disposition: attachment; filename="' . addslashes($filename) . '"';
    $headers[] = 'Content-Type: ' . $contentType;
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_USERAGENT => 'AI Publisher/1.0',
    ];
    if (defined('CURL_IPRESOLVE_V4')) {
        $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }
    curl_setopt_array($ch, $curlOptions);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception($error);
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $message = is_array($decoded) ? ($decoded['message'] ?? $decoded['error']['message'] ?? $response) : $response;
        throw new Exception($message);
    }
    return is_array($decoded) ? $decoded : ['raw' => $response];
}

function publisher_publication_insert($dbo, $item, $channelId, $status, $externalId = null, $externalUrl = null, $errorMessage = null) {
    $now = date('Y-m-d H:i:s');
    return $dbo->execSQL(
        'INSERT INTO content_publications
         (account_id, property_id, content_item_id, distribution_channel_id, external_id, external_url, status, scheduled_at, published_at, error_message, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $item['account_id'],
            $item['property_id'],
            $item['id'],
            $channelId,
            $externalId,
            $externalUrl,
            $status,
            null,
            $status === 'published' ? $now : null,
            $errorMessage,
            $now,
            $now,
        ]
    );
}

function publisher_publish_wordpress($dbo, $item, $channel) {
    $credentials = publisher_channel_credentials($channel);
    $settings = publisher_channel_settings($channel);
    $siteUrl = publisher_normalize_base_url($credentials['site_url'] ?? '');
    $username = trim((string)($credentials['username'] ?? ''));
    $password = trim((string)($credentials['application_password'] ?? ''));
    if ($siteUrl === '' || $username === '' || $password === '') {
        throw new Exception('Missing WordPress site_url, username, or application_password.');
    }

    $auth = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
    $mediaId = null;
    $media = publisher_first_media_asset($dbo, (int)$item['id'], (int)$item['account_id'], (int)$item['property_id']);
    $localPath = $media ? publisher_media_local_path($media) : '';
    if ($localPath !== '' && is_file($localPath)) {
        $mime = mime_content_type($localPath) ?: 'image/jpeg';
        $upload = publisher_http_upload(
            $siteUrl . '/wp-json/wp/v2/media',
            [$auth],
            file_get_contents($localPath),
            $mime,
            basename($localPath)
        );
        $mediaId = $upload['id'] ?? null;
    }

    $payload = [
        'title' => $item['title'],
        'content' => $item['body'],
        'excerpt' => $item['summary'],
        'status' => $settings['default_status'] ?? 'draft',
        'slug' => $item['slug'],
    ];
    if (!empty($settings['author_id'])) {
        $payload['author'] = (int)$settings['author_id'];
    }
    $itemCategories = publisher_content_item_categories($dbo, (int)$item['id'], (int)$item['account_id'], (int)$item['property_id']);
    $itemCategoryIds = $itemCategories ? publisher_wordpress_category_ids_by_slug($siteUrl, $auth, $itemCategories) : [];
    if ($itemCategoryIds) {
        $payload['categories'] = $itemCategoryIds;
    } elseif (!empty($settings['category_ids']) && is_array($settings['category_ids'])) {
        $payload['categories'] = array_values(array_map('intval', $settings['category_ids']));
    }
    if ($mediaId) {
        $payload['featured_media'] = (int)$mediaId;
    }
    $seoMeta = publisher_wordpress_seo_meta($item, $settings);
    if ($seoMeta) {
        $payload['meta'] = $seoMeta;
    }

    try {
        $post = publisher_http_json('POST', $siteUrl . '/wp-json/wp/v2/posts', [$auth], $payload);
    } catch (Exception $ex) {
        if (isset($payload['author']) && stripos($ex->getMessage(), 'not allowed to create posts as this user') !== false) {
            unset($payload['author']);
            $post = publisher_http_json('POST', $siteUrl . '/wp-json/wp/v2/posts', [$auth], $payload);
        } else {
            throw $ex;
        }
    }
    return [
        'external_id' => $post['id'] ?? null,
        'external_url' => $post['link'] ?? null,
        'response' => $post,
    ];
}

function publisher_publish_facebook_page($dbo, $item, $channel) {
    $credentials = publisher_channel_credentials($channel);
    $settings = publisher_channel_settings($channel);
    $pageId = trim((string)($credentials['page_id'] ?? ''));
    $token = trim((string)($credentials['page_access_token'] ?? ''));
    $version = trim((string)($credentials['graph_version'] ?? $settings['graph_version'] ?? 'v24.0'));
    if ($pageId === '' || $token === '') {
        throw new Exception('Missing Facebook page_id or page_access_token.');
    }

    $messageTemplate = $settings['default_message_template'] ?? "{title}\n\n{summary}";
    $externalUrl = publisher_latest_external_url($dbo, (int)$item['id'], (int)$item['account_id'], (int)$item['property_id']);
    $message = str_replace(['{title}', '{summary}', '{url}'], [$item['title'], $item['summary'], $externalUrl], $messageTemplate);
    $media = publisher_first_media_asset($dbo, (int)$item['id'], (int)$item['account_id'], (int)$item['property_id']);
    $localPath = $media ? publisher_media_local_path($media) : '';

    $endpoint = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($pageId);
    if ($localPath !== '' && is_file($localPath)) {
        $ch = curl_init($endpoint . '/photos');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'caption' => $message,
                'access_token' => $token,
                'source' => new CURLFile($localPath),
            ],
        ]);
    } else {
        $ch = curl_init($endpoint . '/feed');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'message' => $message,
                'access_token' => $token,
            ],
        ]);
    }

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception($error);
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $message = is_array($decoded) ? ($decoded['error']['message'] ?? $response) : $response;
        throw new Exception($message);
    }

    $externalId = $decoded['post_id'] ?? $decoded['id'] ?? null;
    return [
        'external_id' => $externalId,
        'external_url' => $externalId ? 'https://www.facebook.com/' . $externalId : null,
        'response' => $decoded,
    ];
}

function publisher_publish_content_item($dbo, $contentItemId, $channelId, $accountId, $propertyId, $settingsOverrides = []) {
    $itemRows = $dbo->getRS('SELECT * FROM content_items WHERE id = ? AND account_id = ? AND property_id = ? LIMIT 1', [$contentItemId, $accountId, $propertyId]);
    if (!$itemRows) {
        throw new Exception('Content item not found.');
    }
    $channelRows = $dbo->getRS('SELECT * FROM distribution_channels WHERE id = ? AND account_id = ? AND property_id = ? AND status = ? LIMIT 1', [$channelId, $accountId, $propertyId, 'active']);
    if (!$channelRows) {
        throw new Exception('Distribution channel not found or inactive.');
    }

    $item = $itemRows[0];
    $channel = $channelRows[0];
    if ($settingsOverrides && is_array($settingsOverrides)) {
        $channelSettings = publisher_channel_settings($channel);
        $channel['settings_json'] = json_encode(array_merge($channelSettings, $settingsOverrides), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    try {
        if ($channel['type'] === 'wordpress') {
            $result = publisher_publish_wordpress($dbo, $item, $channel);
        } elseif ($channel['type'] === 'facebook') {
            $result = publisher_publish_facebook_page($dbo, $item, $channel);
        } else {
            throw new Exception('Unsupported channel type: ' . $channel['type']);
        }

        publisher_publication_insert($dbo, $item, (int)$channel['id'], 'published', $result['external_id'] ?? null, $result['external_url'] ?? null, null);
        $dbo->execSQL('UPDATE content_items SET status = ?, published_at = ?, updated_at = ? WHERE id = ?', ['published', date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $contentItemId]);
        return $result;
    } catch (Exception $ex) {
        publisher_publication_insert($dbo, $item, (int)$channel['id'], 'failed', null, null, $ex->getMessage());
        throw $ex;
    }
}
