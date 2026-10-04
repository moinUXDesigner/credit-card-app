<?php

// Docker supplies DB_DATABASE to every process. Set the process environment
// before Laravel boots so test connections never touch the tracked SQLite file.
foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => ''] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

require __DIR__.'/../vendor/autoload.php';
