<?php

declare(strict_types=1);

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function post(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $default;
}

function query(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

function redirect(string $route): never
{
    header('Location: /index.php?r=' . urlencode($route));
    exit;
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    $item = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    return $item;
}

function fmt_date(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $ts = strtotime($date);
    return $ts ? date('Y-m-d', $ts) : $date;
}
