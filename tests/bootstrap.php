<?php

// The tests run inside a Statamic site that has this addon installed: a copy of the demo
// site in stack/site (see tools/test.sh), so nothing in the demo itself changes.
$app = getenv('ALTLINGO_TEST_APP') ?: '/tmp/altlingo-test';

require $app.'/vendor/autoload.php';

spl_autoload_register(function (string $class) {
    $prefix = 'Facilo\\AltLingo\\Tests\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
