<?php declare(strict_types=1);

namespace PayTrace\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1746687998AlterLastDigits extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1746687998;
    }
  public function update(Connection $connection): void
  {
    $connection->executeStatement('
        ALTER TABLE `payTrace_customer_vault`
        ADD COLUMN `last_four` VARCHAR(255) NULL AFTER `card_type`
    ');
  }
}
