<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $db = $config['db'];
        $driver = strtolower((string) ($db['driver'] ?? 'mysql'));

        try {
            if ($driver === 'sqlite') {
                $path = (string) ($db['path'] ?? ($config['base_path'] . '/community_health.sqlite'));
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                self::$pdo = new PDO('sqlite:' . $path, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                self::$pdo->exec('PRAGMA foreign_keys = ON');

                // Make MySQL-style CONCAT(...) queries work on SQLite.
                self::$pdo->sqliteCreateFunction(
                    'CONCAT',
                    static function (...$args): string {
                        $parts = [];
                        foreach ($args as $arg) {
                            $parts[] = $arg === null ? '' : (string) $arg;
                        }
                        return implode('', $parts);
                    }
                );

                return self::$pdo;
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['dbname'],
                $db['charset']
            );

            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new PDOException('数据库连接失败: ' . $e->getMessage(), (int) $e->getCode());
        }

        return self::$pdo;
    }
}
