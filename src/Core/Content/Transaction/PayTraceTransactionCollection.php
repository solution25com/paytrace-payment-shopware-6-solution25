<?php declare(strict_types=1);

namespace PayTrace\Core\Content\Transaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PayTraceTransactionEntity $entity)
 * @method void set(string $key, PayTraceTransactionEntity $entity)
 * @method PayTraceTransactionEntity[] getIterator()
 * @method PayTraceTransactionEntity[] getElements()
 * @method PayTraceTransactionEntity|null get(string $key)
 * @method PayTraceTransactionEntity|null first()
 * @method PayTraceTransactionEntity|null last()
 */
class PayTraceTransactionCollection extends EntityCollection
{
  protected function getExpectedClass(): string
  {
    return PayTraceTransactionEntity::class;
  }
}
