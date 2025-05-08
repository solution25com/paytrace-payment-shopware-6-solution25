<?php declare(strict_types=1);

namespace PayTrace\Core\Content\CustomerVault;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;

class CustomerVaultDefinition extends EntityDefinition
{
  public const ENTITY_NAME = 'payTrace_customer_vault';

  public function getEntityName(): string
  {
    return self::ENTITY_NAME;
  }

  public function getEntityClass(): string
  {
    return CustomerVaultEntity::class;
  }

  public function getCollectionClass(): string
  {
    return CustomerVaultCollection::class;
  }

  protected function defineFields(): FieldCollection
  {
    return new FieldCollection([
      (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
      (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required()),
      (new StringField('vaulted_customer_id', 'vaultedCustomerId'))->addFlags(new Required()),
      (new StringField('card_holder_name', 'cardHolderName'))->addFlags(new Required()),
      (new StringField('card_type', 'cardType')),
      (new StringField('last_four', 'lastDigits')),
      (new StringField('customer_label', 'customerLabel'))->addFlags(new Required()),

      new OneToOneAssociationField('customer', 'customer_id', 'id', CustomerDefinition::class, false)
    ]);
  }
}
