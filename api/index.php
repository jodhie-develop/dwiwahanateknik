<?php

/*
 * Entry point untuk Vercel (runtime komunitas vercel-php).
 * vercel.json meneruskan semua request non-statis ke file ini, lalu Laravel menangani seperti biasa.
 *
 * Filesystem Vercel read-only kecuali /tmp, jadi cache bootstrap, view terkompilasi, dan log
 * diarahkan ke /tmp / stderr di sini. Nilai di Environment Variables Vercel tetap diutamakan.
 */

$bawaanVercel = [
    'APP_CONFIG_CACHE' => '/tmp/config.php',
    'APP_EVENTS_CACHE' => '/tmp/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/services.php',
    'VIEW_COMPILED_PATH' => '/tmp/views',
    'LOG_CHANNEL' => 'stderr',
];

foreach ($bawaanVercel as $kunci => $nilai) {
    if (getenv($kunci) === false || getenv($kunci) === '') {
        putenv("{$kunci}={$nilai}");
        $_ENV[$kunci] = $_SERVER[$kunci] = $nilai;
    }
}

if (! is_dir('/tmp/views')) {
    @mkdir('/tmp/views', 0755, true);
}

require __DIR__.'/../public/index.php';
