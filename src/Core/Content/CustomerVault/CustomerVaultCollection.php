<?php

declare(strict_types=1);

namespace solu1Paytrace\Core\Content\CustomerVault;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CustomerVaultEntity>
 *
 * @method void add(CustomerVaultEntity $entity)
 * @method void set(string $key, CustomerVaultEntity $entity)
 * @method CustomerVaultEntity[] getIterator()
 * @method CustomerVaultEntity[] getElements()
 * @method CustomerVaultEntity|null get(string $key)
 * @method CustomerVaultEntity|null first()
 * @method CustomerVaultEntity|null last()
 */
class CustomerVaultCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CustomerVaultEntity::class;
    }
}
