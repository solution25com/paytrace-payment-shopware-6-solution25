<?php

declare(strict_types=1);

namespace PayTrace\Core\Content\CustomerVault;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CustomerVaultEntity extends Entity
{
    use EntityIdTrait;

    protected $id;

    protected ?string $customerId;

    protected $vaultedCustomerId;

    protected $cardType;
    protected $cardHolderName;
    protected $customerLabel;


    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getVaultedCustomerId()
    {
        return $this->vaultedCustomerId;
    }

    public function setVaultedCustomerId(string $vaultedCustomerId): void
    {
        $this->vaultedCustomerId = $vaultedCustomerId;
    }

    public function getCardType()
    {
        return $this->cardType;
    }
    public function setCardType(string $cardType): void
    {
        $this->cardType = $cardType;
    }

    public function getCustomerLabel()
    {
        return $this->customerLabel;
    }

    public function setCustomerLabel(string $customerLabel): void
    {
        $this->customerLabel = $customerLabel;
    }
    public function getCardHolderName()
    {
        return $this->cardHolderName;
    }
    public function setCardHolderName(string $cardHolderName): void
    {
        $this->cardHolderName = $cardHolderName;
    }
}
