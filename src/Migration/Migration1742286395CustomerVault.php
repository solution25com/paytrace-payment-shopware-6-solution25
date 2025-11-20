<?php

declare(strict_types=1);

namespace solu1Paytrace\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1742286395CustomerVault extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1742286395;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `payTrace_customer_vault` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `vaulted_customer_id` VARCHAR(255) NOT NULL,
                `card_holder_name` VARCHAR(255) NOT NULL,
                `card_type` VARCHAR(255) DEFAULT NULL,
                `customer_label` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3),
                `updated_at` DATETIME(3) DEFAULT NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.payTrace_customer_vault.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');
    }
}
