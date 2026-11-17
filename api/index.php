<?php

/*
 * Entry point untuk Vercel (runtime komunitas vercel-php).
 * vercel.json meneruskan semua request non-statis ke file ini, lalu Laravel menangani seperti biasa.
 */

require __DIR__.'/../public/index.php';
