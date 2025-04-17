<?php declare(strict_types=1);

namespace PayTrace\Core\Content\Transaction;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PayTraceTransactionEntity extends Entity
{
  use EntityIdTrait;
  protected ?string $orderId;
  protected ?string $paymentMethodName;
  protected ?string $transactionId;
  protected ?string $status;

  public function getOrderId(): ?string
  {
    return $this->orderId;
  }

  public function setOrderId(?string $orderId): void
  {
    $this->orderId = $orderId;
  }

  public function getPaymentMethodName(): ?string
  {
    return $this->paymentMethodName;
  }

  public function setPaymentMethodName(string $paymentMethodName): void
  {
    $this->paymentMethodName = $paymentMethodName;
  }

  public function getTransactionId(): ?string
  {
    return $this->transactionId;
  }

  public function setTransactionId(string $transactionId): void
  {
    $this->transactionId = $transactionId;
  }

  public function getStatus(): ?string
  {
    return $this->status;
  }

  public function setStatus(string $status): void
  {
    $this->status = $status;
  }

}