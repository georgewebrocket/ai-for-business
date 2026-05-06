<?php

if (!isset($t) || !is_array($t)) {
    $t = [];
}

$t += [
    'USER PREFERENCES' => ['en' => 'User preferences', 'gr' => 'Προτιμήσεις χρήστη'],
    'LOGOUT' => ['en' => 'Logout', 'gr' => 'Έξοδος'],
];

$menu0 = $db1->getRS("SELECT * FROM menu WHERE active=1 AND parent=0 ORDER BY morder");

function publisher_menu_allowed($menuRow, $userAccess) {
    if (!isset($menuRow['auth']) || $menuRow['auth'] === '' || $menuRow['auth'] === null) {
        return true;
    }

    return strpos($userAccess, "[" . $menuRow['auth'] . "]") !== false;
}

function publisher_menu_label($menuRow, $lang) {
    if (isset($menuRow[$lang]) && $menuRow[$lang] !== '') {
        return $menuRow[$lang];
    }
    if (isset($menuRow['title']) && $menuRow['title'] !== '') {
        return $menuRow['title'];
    }
    if (isset($menuRow['name']) && $menuRow['name'] !== '') {
        return $menuRow['name'];
    }
    return '';
}

function publisher_render_menu_link($menuRow, $label, $liClass = '') {
    $linkValue = $menuRow['link'] ?? '#';
    $linkParts = explode("###", $linkValue);
    $link = $linkParts[0];
    $mode = $linkParts[1] ?? '';
    $liClassAttr = $liClass !== '' ? ' class="' . htmlspecialchars($liClass, ENT_QUOTES, 'UTF-8') . '"' : '';
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

    if ($mode === 'popup') {
        echo '<li' . $liClassAttr . '><a class="modalBtn" data-title="' . $safeLabel . '" data-href="' . htmlspecialchars($linkValue, ENT_QUOTES, 'UTF-8') . '">' . $safeLabel . '</a>';
        return;
    }

    if ($mode === 'newwindow') {
        echo '<li' . $liClassAttr . '><a target="_blank" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . $safeLabel . '</a>';
        return;
    }

    echo '<li' . $liClassAttr . '><a href="' . htmlspecialchars($linkValue, ENT_QUOTES, 'UTF-8') . '">' . $safeLabel . '</a>';
}

$userInitials = '';
$nameParts = preg_split('/\s+/', trim((string)$user_fullname));
if ($nameParts && $nameParts[0] !== '') {
    $userInitials .= mb_substr($nameParts[0], 0, 1);
}
if (isset($nameParts[1]) && $nameParts[1] !== '') {
    $userInitials .= mb_substr($nameParts[1], 0, 1);
}
if ($userInitials === '') {
    $userInitials = 'U';
}

?>

<nav class="navbar navbar-default">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="<?php echo app::$host; ?>">
                <img src="img/logo.png" alt="logo" style="width:150px" />
            </a>
        </div>

        <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav">
                <?php if ($menu0) { ?>
                    <?php foreach ($menu0 as $parentMenu) { ?>
                        <?php
                        $children = $db1->getRS("SELECT * FROM menu WHERE active=1 AND parent=? ORDER BY morder", [$parentMenu['id']]);
                        $visibleChildren = [];
                        if ($children) {
                            foreach ($children as $childMenu) {
                                if (publisher_menu_allowed($childMenu, $user_access)) {
                                    $visibleChildren[] = $childMenu;
                                }
                            }
                        }

                        $parentAllowed = publisher_menu_allowed($parentMenu, $user_access);
                        if (!$parentAllowed && !$visibleChildren) {
                            continue;
                        }

                        $parentLabel = publisher_menu_label($parentMenu, $lang);
                        if ($parentLabel === '') {
                            continue;
                        }

                        if ($visibleChildren) {
                            echo '<li class="dropdown">';
                            echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">'
                                . htmlspecialchars($parentLabel, ENT_QUOTES, 'UTF-8') . ' <span class="caret"></span></a>';
                            echo '<ul class="dropdown-menu" role="menu">';
                            foreach ($visibleChildren as $childMenu) {
                                $childLabel = publisher_menu_label($childMenu, $lang);
                                if ($childLabel !== '') {
                                    publisher_render_menu_link($childMenu, $childLabel);
                                    echo '</li>';
                                }
                            }
                            echo '</ul>';
                            echo '</li>';
                        } elseif ($parentAllowed) {
                            publisher_render_menu_link($parentMenu, $parentLabel);
                            echo '</li>';
                        }
                        ?>
                    <?php } ?>
                <?php } ?>
            </ul>

            <ul class="nav navbar-nav navbar-right">
                <?php if (!empty($current_account_name)) { ?>
                    <li>
                        <a href="<?php echo app::$host; ?>login/select-account.php" title="Switch account">
                            <?php echo htmlspecialchars($current_account_name, ENT_QUOTES, 'UTF-8'); ?>
                            <small style="text-transform:capitalize;">(<?php echo htmlspecialchars($current_account_role, ENT_QUOTES, 'UTF-8'); ?>)</small>
                        </a>
                    </li>
                <?php } ?>

                <?php if (!empty($current_property_name)) { ?>
                    <li>
                        <a href="<?php echo app::$host; ?>properties.php" title="Current property">
                            <?php echo htmlspecialchars($current_property_name, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php } ?>

                <li>
                    <a class="modalBtn"
                       data-title="<?php echo func::tr('USER PREFERENCES', $lang, $t) ?>" data-width="1000" data-height="700"
                       data-href="user.php?id=<?php echo $userid; ?>" style="cursor: pointer">
                        <span style="background:#333; color:#fff; padding:5px; border-radius:30px;"><?php echo htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                </li>

                <li><a href="<?php echo app::$host.'login/logout.php';?>"><?php echo func::tr('LOGOUT', $lang, $t) ?></a></li>
            </ul>
        </div>
    </div>
</nav>
