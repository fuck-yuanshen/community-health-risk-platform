<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        if (!is_file($viewFile)) {
            http_response_code(404);
            echo '视图不存在: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            return;
        }

        require __DIR__ . '/../Views/layout/header.php';
        require $viewFile;
        require __DIR__ . '/../Views/layout/footer.php';
    }
}
