<?php declare(strict_types=1);

namespace PayTrace;

use PayTrace\PaymentMethods\PaymentMethodInterface;
use PayTrace\PaymentMethods\PaymentMethods;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Doctrine\DBAL\Connection;


class PayTrace extends Plugin
{
    public function install(InstallContext $installContext): void
    {
      foreach (PaymentMethods::PAYMENT_METHODS as $paymentMethod) {
        $this->addPaymentMethod(new $paymentMethod(), $installContext->getContext());
      }
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
      foreach (PaymentMethods::PAYMENT_METHODS as $paymentMethod) {
        $this->setPaymentMethodIsActive(false, $uninstallContext->getContext(), new $paymentMethod());
        if (!$uninstallContext->keepUserData()) {
            /** @var Connection $connection */
          $connection = $this->container->get(Connection::class);
          $connection->executeStatement('DROP TABLE IF EXISTS payTrace_customer_vault');
          $connection->executeStatement('DROP TABLE IF EXISTS payTrace_transaction');

          $connection->executeStatement(
            'DELETE FROM `migration` WHERE `class` LIKE :migrationPattern;',
            [
              'migrationPattern' => 'PayTrace\Migration\%',
            ]
          );
        }
      }

      if ($uninstallContext->keepUserData()) {
        return;
      }

      parent::uninstall($uninstallContext);

    }

    public function activate(ActivateContext $activateContext): void
    {
      foreach (PaymentMethods::PAYMENT_METHODS as $paymentMethod) {
        $this->setPaymentMethodIsActive(true, $activateContext->getContext(), new $paymentMethod());
      }
      parent::activate($activateContext);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
      foreach (PaymentMethods::PAYMENT_METHODS as $paymentMethod) {
        $this->setPaymentMethodIsActive(false, $deactivateContext->getContext(), new $paymentMethod());
      }
      parent::deactivate($deactivateContext);
    }

    public function update(UpdateContext $updateContext): void
    {
    }

    public function postInstall(InstallContext $installContext): void
    {
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
    }

  private function addPaymentMethod(PaymentMethodInterface $paymentMethod, Context $context): void
  {

    $paymentMethodId = $this->getPaymentMethodId($paymentMethod->getPaymentHandler(), $context);

    $pluginIdProvider = $this->getDependency(PluginIdProvider::class);
    $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);

    if ($paymentMethodId) {
      $this->setPluginId($paymentMethodId, $pluginId, $context);
      return;
    }

    $pluginIdProvider = $this->container->get(PluginIdProvider::class);
      /** @var PluginIdProvider $pluginIdProvider */
    $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);

    $paymentData = [
      'handlerIdentifier' => $paymentMethod->getPaymentHandler(),
      'name' => $paymentMethod->getName(),
      'description' => $paymentMethod->getDescription(),
      'pluginId' => $pluginId,
      'afterOrderEnabled' => true
    ];

    $paymentRepository = $this->getDependency('payment_method.repository');
    $paymentRepository->create([$paymentData], $context);
  }

  private function setPluginId(string $paymentMethodId, string $pluginId, Context $context): void
  {
    $paymentRepository = $this->getDependency('payment_method.repository');
    $paymentMethodData = [
      'id' => $paymentMethodId,
      'pluginId' => $pluginId,
    ];

    $paymentRepository->update([$paymentMethodData], $context);
  }

  private function setPaymentMethodIsActive(bool $active, Context $context, PaymentMethodInterface $paymentMethod): void
  {

    $paymentRepository = $this->getDependency('payment_method.repository');
    $paymentMethodId = $this->getPaymentMethodId($paymentMethod->getPaymentHandler(), $context);

    if (!$paymentMethodId) {
      return;
    }

    $paymentMethodData = [
      'id' => $paymentMethodId,
      'active' => $active,
    ];

    $paymentRepository->update([$paymentMethodData], $context);
  }

  private function getPaymentMethodId(string $paymentMethodHandler, Context $context): ?string
  {
    $paymentRepository = $this->getDependency('payment_method.repository');
    $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter(
      'handlerIdentifier',
      $paymentMethodHandler
    ));

    $paymentIds = $paymentRepository->searchIds($paymentCriteria, $context);

    if ($paymentIds->getTotal() === 0) {
      return null;
    }

    return $paymentIds->getIds()[0];
  }

  public function getDependency(string $name): mixed
  {
    return $this->container->get($name);
  }
}
