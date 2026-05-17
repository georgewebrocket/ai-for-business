<?php

require_once(__DIR__ . '/php/config.php');
require_once(__DIR__ . '/php/db.php');

$db = new DB(conn1::$connstr, conn1::$username, conn1::$password);
$existing = $db->getRS('SELECT COUNT(*) AS total FROM help');
$existingCount = $existing ? (int)$existing[0]['total'] : 0;

if ($existingCount > 0) {
    echo "help table already has {$existingCount} rows. No changes made.\n";
    exit;
}

$sections = [
    [
        'title' => 'Εισαγωγή στο AI-Publisher',
        'content' => '<p>Το AI-Publisher είναι μια εφαρμογή για οργάνωση, δημιουργία και διαχείριση περιεχομένου με τη βοήθεια τεχνητής νοημοσύνης. Η βασική ροή ξεκινά από την επιλογή λογαριασμού και property, συνεχίζει με τη δημιουργία content ideas και καταλήγει στη δημιουργία, επεξεργασία και δημοσίευση content items.</p><p>Κάθε property έχει τις δικές του ρυθμίσεις, κατηγορίες, templates, writing styles, image styles και κανάλια διανομής. Πριν ξεκινήσετε, βεβαιωθείτε ότι εργάζεστε στο σωστό property.</p>',
        'title_en' => 'Introduction to AI-Publisher',
        'content_en' => '<p>AI-Publisher is an application for planning, creating, and managing content with the help of artificial intelligence. The main workflow starts with selecting an account and property, continues with generating content ideas, and ends with creating, editing, and publishing content items.</p><p>Each property has its own settings, categories, templates, writing styles, image styles, and distribution channels. Before starting, make sure you are working in the correct property.</p>',
    ],
    [
        'title' => 'Λογαριασμοί, χρήστες και properties',
        'content' => '<p>Οι λογαριασμοί οργανώνουν την πρόσβαση των χρηστών και τα διαθέσιμα properties. Από την ομάδα μπορείτε να προσκαλέσετε χρήστες και να τους αναθέσετε ρόλους, όπως owner, admin, editor, author ή viewer.</p><p>Τα properties αντιπροσωπεύουν ένα site, brand ή project περιεχομένου. Οι περισσότερες λειτουργίες της εφαρμογής, όπως κατηγορίες, templates και content items, συνδέονται με το ενεργό property.</p>',
        'title_en' => 'Accounts, Users, and Properties',
        'content_en' => '<p>Accounts organize user access and the available properties. From the team area, you can invite users and assign roles such as owner, admin, editor, author, or viewer.</p><p>Properties represent a site, brand, or content project. Most application features, such as categories, templates, and content items, are connected to the active property.</p>',
    ],
    [
        'title' => 'Ρυθμίσεις AI και προεπιλεγμένα μοντέλα',
        'content' => '<p>Στις ρυθμίσεις του property μπορείτε να ορίσετε τα προεπιλεγμένα μοντέλα κειμένου και εικόνας που θα χρησιμοποιούνται στα διάφορα στάδια παραγωγής. Οι ρυθμίσεις μπορούν να εξειδικευτούν ανά στάδιο, για παράδειγμα στη δημιουργία content ideas ή στη δημιουργία άρθρων.</p><p>Αν δεν επιλεγεί ειδικό μοντέλο σε κάποιο στάδιο, η εφαρμογή χρησιμοποιεί τις προηγούμενες ή γενικές προεπιλογές του property.</p>',
        'title_en' => 'AI Settings and Default Models',
        'content_en' => '<p>In the property settings, you can define the default text and image models used across the content production stages. Settings can be customized per stage, for example for content idea generation or article generation.</p><p>If no specific model is selected for a stage, the application uses the previous stage defaults or the general property defaults.</p>',
    ],
    [
        'title' => 'Κατηγορίες περιεχομένου',
        'content' => '<p>Οι κατηγορίες περιεχομένου βοηθούν στην οργάνωση των ιδεών και των άρθρων. Κάθε content idea και content item μπορεί να συνδεθεί με κατηγορία, ώστε να υπάρχει καθαρή θεματολογία και καλύτερη στόχευση.</p><p>Προτείνεται να δημιουργείτε κατηγορίες που αντιστοιχούν σε πραγματικές ενότητες του site ή σε βασικές θεματικές περιοχές του brand.</p>',
        'title_en' => 'Content Categories',
        'content_en' => '<p>Content categories help organize ideas and articles. Each content idea and content item can be linked to a category, keeping topics clear and better targeted.</p><p>It is recommended to create categories that match real sections of the site or core thematic areas of the brand.</p>',
    ],
    [
        'title' => 'Writing styles και οδηγίες γραφής',
        'content' => '<p>Τα writing styles καθορίζουν τη γλώσσα, τον τόνο και τις βασικές οδηγίες γραφής. Μπορείτε να δημιουργήσετε διαφορετικά styles για ενημερωτικό περιεχόμενο, εμπορικό περιεχόμενο, οδηγούς, άρθρα γνώμης ή πιο τεχνικά κείμενα.</p><p>Όταν ένα writing style χρησιμοποιείται στη δημιουργία content ideas, το AI λαμβάνει υπόψη τις οδηγίες του για να παράγει πιο συνεπές και κατάλληλο περιεχόμενο.</p>',
        'title_en' => 'Writing Styles and Writing Instructions',
        'content_en' => '<p>Writing styles define the language, tone, and main writing instructions. You can create different styles for informational content, commercial content, guides, opinion pieces, or more technical articles.</p><p>When a writing style is used during content idea generation, the AI takes its instructions into account to produce more consistent and suitable content.</p>',
    ],
    [
        'title' => 'Content templates',
        'content' => '<p>Τα content templates ορίζουν τη δομή ενός άρθρου ή άλλου τύπου περιεχομένου. Ένα template μπορεί να περιλαμβάνει ενότητες, προτεινόμενη σειρά, οδηγίες ανά ενότητα και σύνδεση με content type.</p><p>Χρησιμοποιώντας templates, η εφαρμογή μπορεί να δημιουργεί πιο οργανωμένα content ideas και τελικά άρθρα με σταθερή μορφή.</p>',
        'title_en' => 'Content Templates',
        'content_en' => '<p>Content templates define the structure of an article or another type of content. A template may include sections, suggested order, instructions per section, and a link to a content type.</p><p>By using templates, the application can create more organized content ideas and final articles with a consistent structure.</p>',
    ],
    [
        'title' => 'Image styles',
        'content' => '<p>Τα image styles περιγράφουν την οπτική κατεύθυνση που θα χρησιμοποιείται για τη δημιουργία εικόνων. Μπορούν να περιλαμβάνουν ύφος, φωτισμό, σύνθεση, χρωματική προσέγγιση και άλλες οδηγίες.</p><p>Οι οδηγίες εικόνας πρέπει να αποφεύγουν λογότυπα, εμπορικά σήματα, αναγνωρίσιμα πρόσωπα, copyrighted χαρακτήρες και αναφορές στο στυλ ζώντων καλλιτεχνών.</p>',
        'title_en' => 'Image Styles',
        'content_en' => '<p>Image styles describe the visual direction used for image generation. They can include style, lighting, composition, color approach, and other visual instructions.</p><p>Image instructions should avoid logos, trademarks, recognizable people, copyrighted characters, and references to the style of living artists.</p>',
    ],
    [
        'title' => 'Δημιουργία content ideas',
        'content' => '<p>Η δημιουργία content ideas γίνεται από τη σελίδα Δημιουργία Content Ideas. Επιλέγετε αριθμό άρθρων, περίοδο, τρόπο δημιουργίας, μοντέλα AI και content mix. Το content mix συνδυάζει κατηγορία, writing style, content template και image style.</p><p>Η εφαρμογή μπορεί να δημιουργήσει ιδέες άμεσα ή να τις προγραμματίσει για σταδιακή δημιουργία μέσω cron job. Οι προτάσεις αποθηκεύονται ως content ideas και μπορούν να ελεγχθούν πριν μετατραπούν σε άρθρα.</p>',
        'title_en' => 'Creating Content Ideas',
        'content_en' => '<p>Content ideas are created from the Create Content Ideas page. You choose the number of articles, planning period, generation mode, AI models, and content mix. The content mix combines category, writing style, content template, and image style.</p><p>The application can generate ideas immediately or schedule them for gradual creation through a cron job. Suggestions are saved as content ideas and can be reviewed before being converted into articles.</p>',
    ],
    [
        'title' => 'Αποφυγή επανάληψης περιεχομένου',
        'content' => '<p>Για να μειωθεί η επανάληψη, η εφαρμογή λαμβάνει υπόψη υπάρχοντες τίτλους και συχνά χρησιμοποιημένα tags. Τα συχνά tags αποθηκεύονται προσωρινά στις ρυθμίσεις του property και ανανεώνονται με συχνότητα που ορίζει ο χρήστης, όπως ημερήσια ή εβδομαδιαία.</p><p>Το AI χρησιμοποιεί αυτές τις πληροφορίες ώστε να αποφεύγει παρόμοιες θεματικές γωνίες, εκτός αν η νέα ιδέα έχει διαφορετικό κοινό, πρόθεση, μορφή ή search intent.</p>',
        'title_en' => 'Avoiding Content Repetition',
        'content_en' => '<p>To reduce repetition, the application considers existing titles and frequently used tags. Frequent tags are cached in the property settings and refreshed at a user-defined interval, such as daily or weekly.</p><p>The AI uses this information to avoid similar content angles unless the new idea has a different audience, intent, format, or search intent.</p>',
    ],
    [
        'title' => 'Διαχείριση content ideas',
        'content' => '<p>Στη λίστα Content Ideas μπορείτε να αναζητήσετε ιδέες με βάση κατηγορία, τίτλο, ημερομηνία και status. Κάθε idea έχει τίτλο, περίληψη, tags, γλώσσα, τόνο, κατάσταση και πιθανή σύνδεση με content item.</p><p>Μπορείτε να επιλέξετε μία ή περισσότερες ιδέες και να τις μετατρέψετε σε άρθρα ή να τις προγραμματίσετε για δημιουργία άρθρων μέσω cron job.</p>',
        'title_en' => 'Managing Content Ideas',
        'content_en' => '<p>In the Content Ideas list, you can search ideas by category, title, date, and status. Each idea includes a title, summary, tags, language, tone, status, and possibly a linked content item.</p><p>You can select one or more ideas and convert them into articles immediately or schedule article generation through a cron job.</p>',
    ],
    [
        'title' => 'Content items και επεξεργασία άρθρων',
        'content' => '<p>Τα content items είναι τα πραγματικά άρθρα ή τεμάχια περιεχομένου που παράγονται από τις ιδέες ή δημιουργούνται χειροκίνητα. Περιλαμβάνουν τίτλο, slug, περίληψη, SEO πεδία, σώμα άρθρου, status, γλώσσα και media.</p><p>Μετά τη δημιουργία, μπορείτε να ανοίξετε το content item, να ελέγξετε το κείμενο, να κάνετε διορθώσεις και να προετοιμάσετε τη δημοσίευση.</p>',
        'title_en' => 'Content Items and Article Editing',
        'content_en' => '<p>Content items are the actual articles or content pieces generated from ideas or created manually. They include title, slug, summary, SEO fields, article body, status, language, and media.</p><p>After generation, you can open the content item, review the text, make corrections, and prepare it for publication.</p>',
    ],
    [
        'title' => 'Media και εικόνες',
        'content' => '<p>Οι εικόνες που δημιουργούνται ή συνδέονται με άρθρα αποθηκεύονται ως media assets. Ένα content item μπορεί να έχει εικόνα, alt text, caption και επιπλέον metadata.</p><p>Πριν χρησιμοποιήσετε μια εικόνα σε δημόσια δημοσίευση, ελέγξτε ότι ταιριάζει στο περιεχόμενο, ότι δεν περιλαμβάνει μη επιθυμητά στοιχεία και ότι υποστηρίζει σωστά το άρθρο.</p>',
        'title_en' => 'Media and Images',
        'content_en' => '<p>Images generated or linked to articles are stored as media assets. A content item can have an image, alt text, caption, and additional metadata.</p><p>Before using an image in a public publication, check that it fits the content, does not include unwanted elements, and properly supports the article.</p>',
    ],
    [
        'title' => 'Distribution channels και δημοσίευση',
        'content' => '<p>Τα distribution channels χρησιμοποιούνται για τη σύνδεση του περιεχομένου με εξωτερικά κανάλια, όπως ιστοσελίδες ή πλατφόρμες δημοσίευσης. Κάθε κανάλι έχει τις δικές του ρυθμίσεις και πρέπει να ελεγχθεί πριν χρησιμοποιηθεί σε παραγωγή.</p><p>Η δημοσίευση πρέπει να γίνεται αφού το content item έχει ελεγχθεί ως προς κείμενο, SEO, εικόνες και τελική μορφοποίηση.</p>',
        'title_en' => 'Distribution Channels and Publishing',
        'content_en' => '<p>Distribution channels connect content with external destinations, such as websites or publishing platforms. Each channel has its own settings and should be tested before production use.</p><p>Publishing should happen after the content item has been reviewed for text, SEO, images, and final formatting.</p>',
    ],
    [
        'title' => 'Cron jobs και προγραμματισμένη παραγωγή',
        'content' => '<p>Τα cron jobs επιτρέπουν στην εφαρμογή να εκτελεί εργασίες αυτόματα, όπως δημιουργία content ideas ή μετατροπή αποδεκτών ideas σε content items. Αυτό είναι χρήσιμο όταν θέλετε σταδιακή παραγωγή περιεχομένου χωρίς χειροκίνητη εκκίνηση κάθε φορά.</p><p>Οι εργασίες cron καταγράφουν την κατάσταση εκτέλεσης, τα σχετικά account/property και πιθανά μηνύματα σφάλματος, ώστε να είναι πιο εύκολος ο έλεγχος.</p>',
        'title_en' => 'Cron Jobs and Scheduled Generation',
        'content_en' => '<p>Cron jobs allow the application to run tasks automatically, such as generating content ideas or converting accepted ideas into content items. This is useful when you want gradual content production without manually starting each run.</p><p>Cron jobs record execution status, related account/property information, and possible error messages, making monitoring easier.</p>',
    ],
    [
        'title' => 'Πρακτικές οδηγίες ποιότητας',
        'content' => '<p>Πριν δημοσιεύσετε περιεχόμενο, ελέγξτε ότι ο τίτλος είναι σαφής, η περίληψη είναι ακριβής, τα tags είναι σχετικά, το άρθρο δεν επαναλαμβάνει προηγούμενο περιεχόμενο και τα SEO πεδία είναι συμπληρωμένα.</p><p>Το AI επιταχύνει τη διαδικασία, αλλά ο τελικός έλεγχος από άνθρωπο παραμένει απαραίτητος για ακρίβεια, ύφος, εμπορική καταλληλότητα και συμμόρφωση με τις ανάγκες του brand.</p>',
        'title_en' => 'Content Quality Best Practices',
        'content_en' => '<p>Before publishing content, check that the title is clear, the summary is accurate, tags are relevant, the article does not repeat previous content, and SEO fields are complete.</p><p>AI accelerates the process, but final human review remains necessary for accuracy, tone, commercial suitability, and alignment with brand needs.</p>',
    ],
];

$showOrder = 1;
foreach ($sections as $section) {
    $db->execSQL(
        'INSERT INTO help (title, content, title_en, content_en, show_order) VALUES (?, ?, ?, ?, ?)',
        [
            $section['title'],
            $section['content'],
            $section['title_en'],
            $section['content_en'],
            $showOrder,
        ]
    );
    $showOrder++;
}

echo 'Inserted ' . count($sections) . " help rows.\n";

