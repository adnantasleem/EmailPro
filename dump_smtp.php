<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$smtp = App\Models\SmtpConfig::where('sent_today', '>', 100)->first();
if ($smtp) {
    file_put_contents('smtp_debug.json', json_encode($smtp->toArray(), JSON_PRETTY_PRINT));
} else {
    file_put_contents('smtp_debug.json', "No SMTP found");
}
