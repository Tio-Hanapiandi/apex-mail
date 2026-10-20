<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$e = App\Models\Email::with('attachments')->latest()->first();

preg_match_all('/<img[^>]+>/i', $e->body_html, $matches);
echo "IMG tags found in body_html: " . count($matches[0]) . "\n";
foreach ($matches[0] as $img) {
    echo "  " . substr($img, 0, 200) . "\n";
}
