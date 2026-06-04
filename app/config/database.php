<?php

final class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '3306';
        $dbName = getenv('DB_NAME') ?: 'lumina_db';
        $username = getenv('DB_USER') ?: 'lumina_user';
        $password = getenv('DB_PASS') ?: 'lumina_password';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $dbName
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$connection = new PDO($dsn, $username, $password, $options);

            // Đồng bộ charset/collation với MySQL 8.
            self::$connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci");

            // Đồng bộ giờ MySQL theo Việt Nam. Các lệnh SQL dùng NOW(), CURRENT_TIMESTAMP
            // sẽ lấy giờ theo UTC+7 thay vì UTC trong container.
            self::$connection->exec("SET time_zone = '+07:00'");

            return self::$connection;
        } catch (PDOException $exception) {
            http_response_code(500);
            die('Lỗi kết nối cơ sở dữ liệu: ' . $exception->getMessage());
        }
    }
}
