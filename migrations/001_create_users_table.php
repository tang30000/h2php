<?php
/**
 * Migration 示例文件：001_create_users_table
 *
 * 文件命名规则：NNN_描述.php（NNN 为三位序号）
 * 执行：php h2 migrate
 * 回滚：php h2 migrate:rollback
 */
return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name`       VARCHAR(100) NOT NULL,
            `email`      VARCHAR(150) NOT NULL UNIQUE,
            `password`   VARCHAR(255) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `users`");
    }
};
