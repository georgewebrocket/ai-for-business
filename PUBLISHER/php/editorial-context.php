<?php

function editorial_context_text_list($value) {
    if (is_array($value)) {
        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $text = trim((string)($item['title'] ?? $item['name'] ?? $item['heading'] ?? ''));
                if ($text === '') {
                    $text = trim(json_encode($item, JSON_UNESCAPED_UNICODE));
                }
                $items[] = $text;
            } else {
                $items[] = trim((string)$item);
            }
        }
    } else {
        $items = preg_split('/[,;\n]+/', (string)$value);
        $items = array_map('trim', $items ?: []);
    }

    return array_values(array_filter($items, function($item) {
        return $item !== '';
    }));
}

function editorial_context_add_counts(&$counts, $items) {
    foreach ($items as $item) {
        $key = mb_strtolower(trim((string)$item), 'UTF-8');
        if ($key === '') {
            continue;
        }
        if (!isset($counts[$key])) {
            $counts[$key] = ['label' => trim((string)$item), 'count' => 0];
        }
        $counts[$key]['count']++;
    }
}

function editorial_context_top_counts($counts, $limit) {
    uasort($counts, function($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    $result = [];
    foreach (array_slice($counts, 0, $limit) as $item) {
        $result[] = $item['label'];
    }
    return $result;
}

function editorial_context_extract_section_titles($sectionsJson) {
    $decoded = json_decode((string)$sectionsJson, true);
    if (!is_array($decoded)) {
        return [];
    }
    $titles = [];
    foreach ($decoded as $section) {
        if (is_array($section)) {
            $title = trim((string)($section['title'] ?? $section['name'] ?? $section['heading'] ?? ''));
            if ($title !== '') {
                $titles[] = $title;
            }
        }
    }
    return $titles;
}

function editorial_context_phrase_candidates($text) {
    $text = mb_strtolower(strip_tags((string)$text), 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
    $words = preg_split('/\s+/u', trim($text));
    if (!$words || count($words) < 4) {
        return [];
    }

    $skip = [
        'και' => true, 'το' => true, 'η' => true, 'ο' => true, 'τα' => true, 'των' => true, 'στην' => true, 'στο' => true,
        'the' => true, 'and' => true, 'for' => true, 'with' => true, 'that' => true, 'this' => true, 'from' => true,
    ];
    $phrases = [];
    for ($i = 0; $i <= count($words) - 4; $i++) {
        $chunk = array_slice($words, $i, 4);
        if (isset($skip[$chunk[0]]) || isset($skip[$chunk[3]])) {
            continue;
        }
        $phrase = implode(' ', $chunk);
        if (mb_strlen($phrase, 'UTF-8') >= 18) {
            $phrases[] = $phrase;
        }
    }
    return $phrases;
}

function editorial_context_get($dbo, $accountId, $propertyId, $options = []) {
    $days = max(1, (int)($options['days'] ?? 180));
    $limit = max(10, min(300, (int)($options['limit'] ?? 120)));
    $since = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

    $ideaRows = $dbo->getRS(
        'SELECT ci.title, ci.summary, ci.tags, ci.sections, ci.tone, ci.language, ci.instructions, ci.image_prompt, ci.created_at,
                cc.name AS category_name
         FROM content_ideas ci
         LEFT JOIN content_categories cc ON cc.id = ci.category_id
         WHERE ci.account_id = ? AND ci.property_id = ? AND ci.created_at >= ?
         ORDER BY ci.created_at DESC
         LIMIT ' . (int)$limit,
        [$accountId, $propertyId, $since]
    ) ?: [];

    $itemRows = $dbo->getRS(
        'SELECT ci.title, ci.summary, ci.body, ci.status, ci.language, ci.created_at,
                ctype.name AS content_type_name
         FROM content_items ci
         LEFT JOIN content_types ctype ON ctype.id = ci.content_type_id
         WHERE ci.account_id = ? AND ci.property_id = ? AND ci.created_at >= ?
         ORDER BY ci.created_at DESC
         LIMIT ' . (int)$limit,
        [$accountId, $propertyId, $since]
    ) ?: [];

    $mediaRows = $dbo->getRS(
        'SELECT prompt, alt_text, caption, created_at
         FROM media_assets
         WHERE account_id = ? AND property_id = ? AND created_at >= ?
         ORDER BY created_at DESC
         LIMIT ' . (int)$limit,
        [$accountId, $propertyId, $since]
    ) ?: [];

    $titles = [];
    $topics = [];
    $tagCounts = [];
    $sectionCounts = [];
    $phraseCounts = [];
    $imageThemes = [];

    foreach ($ideaRows as $row) {
        if (trim((string)$row['title']) !== '') {
            $titles[] = $row['title'];
        }
        if (trim((string)$row['category_name']) !== '') {
            $topics[] = $row['category_name'];
        }
        editorial_context_add_counts($tagCounts, editorial_context_text_list($row['tags'] ?? ''));
        editorial_context_add_counts($sectionCounts, editorial_context_extract_section_titles($row['sections'] ?? ''));
        editorial_context_add_counts($phraseCounts, editorial_context_phrase_candidates(($row['title'] ?? '') . ' ' . ($row['summary'] ?? '') . ' ' . ($row['instructions'] ?? '')));
        if (trim((string)$row['image_prompt']) !== '') {
            $imageThemes[] = mb_substr(trim((string)$row['image_prompt']), 0, 220, 'UTF-8');
        }
    }

    foreach ($itemRows as $row) {
        if (trim((string)$row['title']) !== '') {
            $titles[] = $row['title'];
        }
        if (trim((string)$row['content_type_name']) !== '') {
            $topics[] = $row['content_type_name'];
        }
        editorial_context_add_counts($phraseCounts, editorial_context_phrase_candidates(($row['title'] ?? '') . ' ' . ($row['summary'] ?? '') . ' ' . mb_substr((string)($row['body'] ?? ''), 0, 2000, 'UTF-8')));
    }

    foreach ($mediaRows as $row) {
        $theme = trim((string)($row['prompt'] ?: $row['caption'] ?: $row['alt_text']));
        if ($theme !== '') {
            $imageThemes[] = mb_substr($theme, 0, 220, 'UTF-8');
        }
    }

    $titles = array_values(array_unique(array_slice($titles, 0, 80)));
    $topics = array_values(array_unique(array_slice($topics, 0, 60)));
    $imageThemes = array_values(array_unique(array_slice($imageThemes, 0, 30)));

    return [
        'avoid_titles' => $titles,
        'avoid_topics' => $topics,
        'overused_tags' => editorial_context_top_counts($tagCounts, 40),
        'recent_section_titles' => editorial_context_top_counts($sectionCounts, 40),
        'recent_image_themes' => $imageThemes,
        'avoid_phrases' => editorial_context_top_counts(array_filter($phraseCounts, function($item) {
            return $item['count'] > 1;
        }), 30),
        'recommended_gaps' => [
            'Use a different angle than the recent titles and topics.',
            'Prefer fresh examples, fresh section titles, and new tag combinations.',
            'Avoid repeating image subjects, composition, and wording from recent prompts.',
        ],
    ];
}

function editorial_context_prompt_block($context) {
    $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return <<<PROMPT
Editorial memory and repetition avoidance context:
{$json}

Use this context to avoid repeating titles, topics, tags, section titles, image themes, and recurring phrases. Create materially new angles and wording.
PROMPT;
}
