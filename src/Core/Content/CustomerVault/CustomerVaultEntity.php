<?php declare(strict_types=1);

namespace PayTrace\Core\Content\CustomerVault;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CustomerVaultEntity extends Entity
{
  protected ?String $customerId;

  protected  ?String $vaultedCustomerId;

  protected ?String $cardType;
  protected ?String $cardHolderName;
  protected ?String $customerLabel;


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

}
