<?php declare(strict_types=1);

namespace PayTrace\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1741683676PayTraceVaulted extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1741683676;
    }

    public function update(Connection $connection): void
    {

    }
}
