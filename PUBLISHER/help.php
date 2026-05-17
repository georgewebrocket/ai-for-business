<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');

publisher_require_permission('help');

$translations = [
    "en" => [
        "page-title" => "Help",
        "page-index" => "Index",
    ],
    "gr" => [
        "page-title" => "Βοήθεια",
        "page-index" => "Περιεχόμενα",
    ],
];

$lang = $_GET['l'] ?? ($user_language ?? "gr");
$lang = in_array($lang, ["en", "gr"], true) ? $lang : "gr";
$translation = $translations[$lang] ?? $translations["gr"];
$pageTitleText = $translation["page-title"] ?? "Help";
$pageIndexText = $translation["page-index"] ?? "Index";

// $section = "Βοήθεια";

switch ($lang) {
    case 'en':
        $sql = "SELECT id,
                       COALESCE(NULLIF(title_en, ''), title) AS title,
                       COALESCE(NULLIF(content_en, ''), content) AS content
                FROM help
                ORDER BY show_order, id";
        break;

    case 'gr':
        $sql = "SELECT id, title, content FROM help ORDER BY show_order, id";
        break;
}

$helpItems = $db1->getRS($sql);

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title><?php echo app::$project_name; ?></title>

        <?php include "_head.php"; ?>

        <style>
            body {
                scroll-behavior: smooth;
            }

            .help-layout {
                display: flex;
                gap: 24px;
                align-items: flex-start;
            }

            .help-nav {
                width: 260px;
                position: sticky;
                top: 80px;
                padding: 16px;
                background-color: #eee;
                border:1px solid #ccc;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            }

            .help-nav h4 {
                margin-top: 0;
            }

            .help-nav ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .help-nav li {
                margin-bottom: 10px;
            }

            .help-nav a {
                color: #333;
                text-decoration: none;
            }

            .help-nav a:hover {
                text-decoration: underline;
            }

            .help-content {
                flex: 1;
                /* padding: 20px;
                background-color: #fff; */

                max-width: 700px;
            }

            .help-section {
                margin-bottom: 32px;
                border-radius: 12px;
                border:1px solid #ccc;
                padding:16px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);

            }

            .help-section:last-child {
                margin-bottom: 0;
            }

            .help-section h3 {
                margin-top: 0;
            }

            @media (max-width: 900px) {
                .help-layout {
                    flex-direction: column;
                }

                .help-nav {
                    width: 100%;
                    position: static;
                }
            }
        </style>
    </head>

    <body>
        <?php include "blocks/header.php"; ?>

        <div class="padding-20">
            <h2><?php echo htmlspecialchars($pageTitleText, ENT_QUOTES, 'UTF-8'); ?></h2>

            <?php if (!$helpItems || count($helpItems) === 0) { ?>
                <div class="alert alert-info">Δεν υπάρχουν διαθέσιμες οδηγίες αυτή τη στιγμή.</div>
            <?php } else { ?>
                <div class="help-layout">
                    <aside class="help-nav">
                        <h2><?php echo htmlspecialchars($pageIndexText, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <ul>
                            <?php foreach ($helpItems as $item) { ?>
                                <li>
                                    <a href="#help-item-<?php echo (int)$item['id']; ?>">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </aside>

                    <section class="help-content">
                        <?php foreach ($helpItems as $item) { ?>
                            <div id="help-item-<?php echo (int)$item['id']; ?>" class="help-section">
                                <h2><?php echo htmlspecialchars($item['title']); ?></h2>
                                <div class="help-section-content">
                                    <?php echo $item['content']; ?>
                                </div>
                            </div>
                        <?php } ?>
                    </section>
                </div>
            <?php } ?>
        </div>

        <?php include "blocks/footer.php"; ?>
    </body>
</html>
