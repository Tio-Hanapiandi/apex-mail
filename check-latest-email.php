<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$e = App\Models\Email::with('attachments')->latest()->first();
if (!$e) {
    echo "No emails found.\n";
    exit;
}

echo "=== LATEST EMAIL ===\n";
echo "ID: {$e->id}\n";
echo "Subject: {$e->subject}\n";
echo "From: {$e->from_email}\n";
echo "Created: {$e->created_at}\n";
echo "Attachments count: " . $e->attachments->count() . "\n";

foreach ($e->attachments as $a) {
    echo "  - Filename: {$a->filename}\n";
    echo "    Content-Type: {$a->content_type}\n";
    echo "    Content-ID: {$a->content_id}\n";
    echo "    Is Inline: " . ($a->is_inline ? 'YES' : 'NO') . "\n";
    echo "    Size: {$a->size_bytes} bytes\n";
    echo "    Path: {$a->storage_path}\n";
    echo "    Exists on disk: " . (file_exists($a->storage_path) ? 'YES' : 'NO') . "\n";
}

echo "\n--- BODY HTML ---\n";
echo substr($e->body_html, 0, 1500) . "\n";
