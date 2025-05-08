<?php declare(strict_types=1);

namespace PayTrace\Core\Content\CustomerVault;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CustomerVaultEntity extends Entity
{
  use EntityIdTrait;

  protected ?String $customerId;

  protected  ?String $vaultedCustomerId;

  protected ?String $cardType;
  protected ?string $lastDigits;
  protected ?String $cardHolderName;
  protected ?String $customerLabel;
  protected ?CustomerEntity $customer = null;


  public function getCustomerId(): ?String{
    return $this->customerId;
  }

  public function setCustomerId(?String $customerId): void{
    $this->customerId = $customerId;
  }

  public function getVaultedCustomerId(): ?string{
    return $this->vaultedCustomerId;
  }

  public function setVaultedCustomerId(string $vaultedCustomerId): void{
    $this->vaultedCustomerId = $vaultedCustomerId;
  }

  public function getCardType(): ?string{
    return $this->cardType;
  }
  public function setCardType(string $cardType): void{
    $this->cardType = $cardType;
  }

  public function getLastDigits(): ?string{
    return $this->lastDigits;
  }

  public function setLastDigits(string $lastDigits): void{
    $this->lastDigits = $lastDigits;
  }

  public function getCustomerLabel(): ?string{
    return $this->customerLabel;
  }

  public function setCustomerLabel(string $customerLabel): void{
    $this->customerLabel = $customerLabel;
  }
  public function getCardHolderName(): ?string{
    return $this->cardHolderName;
  }
  public function setCardHolderName(string $cardHolderName): void{
    $this->cardHolderName = $cardHolderName;
  }

  public function getCustomer(): ?CustomerEntity
  {
    return $this->customer;
  }

  public function setCustomer(?CustomerEntity $customer): void
  {
    $this->customer = $customer;
  }

}
