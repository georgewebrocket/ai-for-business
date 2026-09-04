# Embeddings Project Demo σε PHP

Demo εφαρμογή για να διαβάζουμε προϊόντα από JSON, να φτιάχνουμε ένα ενιαίο κείμενο ανά προϊόν και να γράφουμε την απάντηση του OpenAI Embeddings API πίσω στο JSON ως πεδίο `embeddings`.

Το script βασίζεται στο endpoint `POST /v1/embeddings` της επίσημης OpenAI τεκμηρίωσης και στέλνει batch `input` με ένα κείμενο ανά προϊόν.

## Δομή δεδομένων

Το input JSON μπορεί να είναι:

- array προϊόντων
- object με πεδίο `products` που περιέχει array

Για κάθε προϊόν χρησιμοποιούνται τα πεδία:

- `productName`
- `description`
- `uses`
- `material`
- `dimensions`

Υπάρχουν και μερικά aliases για ελληνικά ή εναλλακτικά ονόματα πεδίων, όπως `περιγραφή`, `χρήσεις`, `υλικό`, `διαστάσεις`, `name`, `title`, `materials`.

Το κείμενο που στέλνεται στο API έχει μορφή:

```text
Ονομασία προϊόντος: ...
Περιγραφή: ...
Χρήσεις: ...
Υλικό: ...
Διαστάσεις: ...
```

## Εκτέλεση σαν κανονική σελίδα

Άνοιξε τον φάκελο `sandbox/embeddings-project` από τον PHP server σου και φόρτωσε το `index.php`.

Για local δοκιμή με τον ενσωματωμένο PHP server:

```powershell
php -S localhost:8080
```

Μετά άνοιξε:

```text
http://localhost:8080
```

Το `index.php` δείχνει:

- πόσα προϊόντα διαβάστηκαν από το `products.json`
- preview του κειμένου που θα σταλεί στο Embeddings API
- φόρμα για δημιουργία του `products.with-embeddings.json`

Το `generate.php` δέχεται το POST της φόρμας, καλεί το OpenAI API και γράφει το output JSON.

## API key

Μπορείς να βάλεις το API key ως environment variable:

```powershell
$env:OPENAI_API_KEY = "sk-..."
```

Ή να αντιγράψεις το `config.example.php` σε `config.php` και να βάλεις εκεί το key:

```php
<?php

return [
    'OPENAI_API_KEY' => 'sk-...',
    'OPENAI_EMBEDDING_MODEL' => 'text-embedding-3-small',
];
```

Το `config.php` είναι στο `.gitignore`.

## Εκτέλεση από CLI

Δοκιμή χωρίς κλήση στο API:

```powershell
php .\src\create-embeddings.php --dry-run
```

Δημιουργία embeddings για το `products.json`:

```powershell
php .\src\create-embeddings.php
```

Με δικό σου αρχείο:

```powershell
php .\src\create-embeddings.php --input products.json --output products.with-embeddings.json
```

Για ενημέρωση του ίδιου αρχείου:

```powershell
php .\src\create-embeddings.php --input products.json --in-place
```

Προεπιλεγμένο μοντέλο: `text-embedding-3-small`.

Αλλαγή μοντέλου:

```powershell
php .\src\create-embeddings.php --input products.json --model text-embedding-3-large
```

## Output

Το output κρατά τα υπάρχοντα πεδία κάθε προϊόντος και προσθέτει:

```json
{
  "embeddings": [0.0123, -0.0456]
}
```

Στην πράξη το array είναι πολύ μεγαλύτερο, γιατί περιέχει το embedding vector που επιστρέφει το μοντέλο.
