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

            if ($key === '') {
                continue;
            }

            // ВАЖНО: $_ENV/$_SERVER заполняем ВСЕГДА (они привязаны к запросу и
            // не разделяются между потоками). putenv()/getenv() на многопоточном
            // Apache под Windows (mpm_winnt) — процессные и общие для всех потоков:
            // когда параллельный запрос завершается, PHP сбрасывает его putenv-значения,
            // из-за чего другой ещё выполняющийся запрос мог внезапно увидеть
            // getenv('DB_USER') === false. Поэтому НЕ полагаемся на чужой putenv
            // (убран прежний guard getenv($key) === false) и всегда пишем в оба места.
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

/**
 * Безопасное чтение переменной окружения. Сначала getenv() (в продакшене
 * значения приходят из реального окружения Docker), затем request-local
 * $_ENV/$_SERVER как защита от гонки putenv/getenv на многопоточном Apache.
 */
if (!function_exists('portus_env')) {
    function portus_env($key, $default = null)
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        return $default;
    }
}

portus_load_env_file(__DIR__ . '/../../.env');
