<?php

// Простой загрузчик .env без сторонних зависимостей.
// Читает KEY=VALUE построчно из файла .env в корне backend-проекта
// (сам .env не должен попадать в git — см. .env.example и .gitignore)
// и кладёт значения в putenv()/$_ENV, откуда их забирает getenv().

if (!function_exists('portus_load_env_file')) {
    function portus_load_env_file($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "\"'");

            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }
}

portus_load_env_file(__DIR__ . '/../../.env');
