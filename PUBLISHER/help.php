<?php

/*ini_set('display_errors',1); 
error_reporting(E_ALL);*/


require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once ('php/controls.php');

$t = []; ////
$lang = $user_language;

$sql = "SELECT * FROM HELP WHERE show_order>0 ORDER BY show_order";
$rs = $dbo->getRS($sql);

$topics = $rs;



// Optional: page title / branding
$appName = 'ROCKETONE Help Center';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title><?= htmlspecialchars($appName) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <?php include "_head.php"; ?>

  <style>
    :root{
      --bg: #fff;
      --panel: #eee;
      --panel-2:#0f1420;
      --text: #222;
      --muted:#94a3b8;
      --accent:#5b9cff;
      --accent-2:#2f6dff;
      --border:#1f2a3b;
      --ring: rgba(91,156,255,.3);
      --radius: 16px;
    }

    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body{
      margin:0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
      color: var(--text);
      /* background: radial-gradient(1200px 800px at 10% -10%, #162136, transparent 40%),
                  radial-gradient(1000px 700px at 120% 20%, #1a2040, transparent 35%),
                  var(--bg); */
      line-height:1.6;
    }

    a{ color: var(--accent); text-decoration: none; }
    a:hover{ text-decoration: underline; }

    .layout{
      display:flex;
      gap: 20px;
      max-width: 1200px;
      margin: 0 auto;
      padding: 24px;
    }

    /* Sidebar */
    .sidebar{
      position: sticky;
      top: 16px;
      align-self: flex-start;
      width: 300px;
      max-height: calc(100vh - 32px);
      padding: 16px;
      /* background: linear-gradient(180deg, var(--panel), var(--panel-2)); */
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: 0 10px 30px rgba(0,0,0,.3), inset 0 1px 0 rgba(255,255,255,.02);
      overflow: auto;
    }
    .brand{
      font-weight: 700;
      font-size: 18px;
      letter-spacing:.2px;
      margin-bottom: 12px;
    }
    .search{
      position: relative;
      margin-bottom: 10px;
    }
    .search input{
      width:100%;
      padding: 10px 12px;
      border-radius: 10px;
      background: #ccc;
      border: 1px solid var(--border);
      color: var(--text);
      outline: none;
    }
    .search input:focus{
      border-color: var(--accent);
      box-shadow: 0 0 0 6px var(--ring);
    }

    .toc{
      list-style: none;
      margin: 8px 0 0;
      padding: 0;
    }
    .toc li{ margin: 2px 0; }
    .toc a{
      display:block;
      padding: 10px 12px;
      border-radius: 10px;
      color: var(--text);
      border: 1px solid transparent;
    }
    .toc a:hover{
      background: #ccc;
      border-color: var(--border);
      text-decoration:none;
    }
    .toc a.active{
      background: rgba(91,156,255,.12);
      border-color: rgba(91,156,255,.35);
      box-shadow: 0 0 0 6px var(--ring);
    }
    .toc-empty{
      color: var(--muted);
      padding: 8px 2px 0;
      display: none;
      font-size: 14px;
    }

    /* Content */
    .content{
      flex:1;
      min-width: 0;
      padding: 16px 20px;
      background: linear-gradient(180deg, rgba(255,255,255,.01), rgba(255,255,255,0));
      border: 1px solid var(--border);
      border-radius: var(--radius);
    }
    .page-title{
      margin-top: 0;
      margin-bottom: 8px;
      letter-spacing:.2px;
    }
    .intro{
      margin-top:0;
      color: var(--muted);
    }

    section.help{
      scroll-margin-top: 16px;
      padding: 16px 0 24px;
      border-top: 1px dashed var(--border);
    }
    section.help:first-of-type{
      border-top: 0;
      padding-top: 8px;
    }
    section.help h2{
      margin: 8px 0 8px;
      letter-spacing:.2px;
    }
    section.help h3{
      margin: 16px 0 6px;
      font-size: 16px;
    }
    code, kbd {
      background: #0b0f19;
      border: 1px solid var(--border);
      padding: 0 6px;
      border-radius: 6px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
      font-size: .9em;
    }
    kbd { padding: 2px 6px; border-bottom-width:2px; }

    /* Responsive */
    @media (max-width: 960px){
      .layout{ flex-direction: column; padding: 12px; }
      .sidebar{
        position: static;
        width: auto;
        max-height: none;
        order: 0;
      }
      .content{ order: 1; }
    }
  </style>
</head>
<body>

<?php include "blocks/header.php"; ?>

  <div class="layout">
    <!-- Sticky Left Menu -->
    <aside class="sidebar" aria-label="Help navigation">
      <div class="brand">📖 <?= htmlspecialchars($appName) ?></div>
      <div class="search">
        <input id="search" type="search" placeholder="Search topics…" aria-label="Search topics">
      </div>

      <ul id="toc" class="toc">
        <?php foreach ($topics as $t): ?>
          <li>
            <a href="#<?= htmlspecialchars($t['id']) ?>" data-title="<?= htmlspecialchars(mb_strtolower($t['title'])) ?>">
              <?= htmlspecialchars($t['title']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <div id="toc-empty" class="toc-empty">No topics match your search.</div>
    </aside>

    <!-- Right Content -->
    <main class="content">
      <h1 class="page-title">Help & Documentation</h1>
      <p class="intro">Use the sticky menu to jump between topics. Type in the search box to filter.</p>

      <?php foreach ($topics as $t): ?>
        <section id="<?= htmlspecialchars($t['id']) ?>" class="help" aria-labelledby="h-<?= htmlspecialchars($t['id']) ?>">
          <h2 id="h-<?= htmlspecialchars($t['id']) ?>"><?= htmlspecialchars($t['title']) ?></h2>
          <div class="help-body">
            <?= $t['html'] /* Intentionally render trusted HTML */ ?>
          </div>
          <p><a href="#top" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">Back to top ↑</a></p>
        </section>
      <?php endforeach; ?>
    </main>
  </div>

  <script>
    // Smooth scroll for in-page anchors
    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => {
        const id = a.getAttribute('href').slice(1);
        const el = document.getElementById(id);
        if (el) {
          e.preventDefault();
          el.scrollIntoView({ behavior: 'smooth', block: 'start' });
          history.replaceState(null, '', '#' + id);
        }
      });
    });

    // Scroll-Spy to highlight active menu item
    (function(){
      const links = Array.from(document.querySelectorAll('#toc a'));
      const sections = links
        .map(a => document.getElementById(a.getAttribute('href').slice(1)))
        .filter(Boolean);

      const byId = Object.fromEntries(links.map(a => [a.getAttribute('href').slice(1), a]));

      const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const id = entry.target.id;
            links.forEach(l => l.classList.remove('active'));
            const active = byId[id];
            if (active) active.classList.add('active');
          }
        });
      }, { rootMargin: '-30% 0px -60% 0px', threshold: 0.01 });

      sections.forEach(sec => io.observe(sec));
    })();

    // Topic filter (search box filters menu items by title)
    (function(){
      const input = document.getElementById('search');
      const list = document.getElementById('toc');
      const empty = document.getElementById('toc-empty');
      const items = Array.from(list.querySelectorAll('a'));

      function applyFilter(q){
        const query = q.trim().toLowerCase();
        let visibleCount = 0;

        items.forEach(a => {
          const title = a.dataset.title || a.textContent.toLowerCase();
          const match = !query || title.includes(query);
          a.parentElement.style.display = match ? '' : 'none';
          if (match) visibleCount++;
        });

        empty.style.display = visibleCount ? 'none' : 'block';
      }

      input.addEventListener('input', e => applyFilter(e.target.value));
    })();
  </script>

<?php include "blocks/footer.php"; ?>
    </body>
    
</html>