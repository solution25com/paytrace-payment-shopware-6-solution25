<?php declare(strict_types=1);

namespace PayTrace\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1747228288CustomPaymentStateTransitions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1747228288;
    }

  public function update(Connection $connection): void
  {
    $stateMachineId = $connection->fetchOne(
      'SELECT id FROM state_machine WHERE technical_name = :technicalName',
      ['technicalName' => 'order_transaction.state']
    );

    if (!$stateMachineId) {
      throw new \RuntimeException('State machine "order_transaction.state" not found.');
    }

    $refundedStateId = $connection->fetchOne(
      'SELECT id FROM state_machine_state WHERE technical_name = :technicalName AND state_machine_id = :stateMachineId',
      ['technicalName' => 'refunded', 'stateMachineId' => $stateMachineId]
    );

    $paidStateId = $connection->fetchOne(
      'SELECT id FROM state_machine_state WHERE technical_name = :technicalName AND state_machine_id = :stateMachineId',
      ['technicalName' => 'paid', 'stateMachineId' => $stateMachineId]
    );

    $exists = $connection->fetchOne(
      'SELECT id FROM state_machine_transition
             WHERE state_machine_id = :stateMachineId
             AND from_state_id = :fromStateId
             AND to_state_id = :toStateId
             AND action_name = :actionName',
      [
        'stateMachineId' => $stateMachineId,
        'fromStateId' => $refundedStateId,
        'toStateId' => $paidStateId,
        'actionName' => 'reopen_payment'
      ]
    );

    if (!$exists) {
      $connection->executeStatement(
        'INSERT INTO state_machine_transition (id, state_machine_id, action_name, from_state_id, to_state_id, created_at)
                 VALUES (:id, :stateMachineId, :actionName, :fromStateId, :toStateId, NOW())',
        [
          'id' => random_bytes(16),
          'stateMachineId' => $stateMachineId,
          'actionName' => 'reopen_payment',
          'fromStateId' => $refundedStateId,
          'toStateId' => $paidStateId,
        ],
        [
          'id' => \Doctrine\DBAL\ParameterType::BINARY,
          'stateMachineId' => \Doctrine\DBAL\ParameterType::BINARY,
          'fromStateId' => \Doctrine\DBAL\ParameterType::BINARY,
          'toStateId' => \Doctrine\DBAL\ParameterType::BINARY,
        ]
      );
    }
  }

  public function updateDestructive(Connection $connection): void
  {}
}



