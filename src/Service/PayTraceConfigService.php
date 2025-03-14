<?php

namespace PayTrace\Service;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class PayTraceConfigService
{
  private const ENV_PROD_SUFFIX = 'Live';
  private const ENV_SANDBOX_SUFFIX = 'Sandbox';
  private const CONFIG_PATH_PREFIX = 'PayTrace.config.';

  private string $environmentSuffix;
  private SystemConfigService $systemConfigService;

  public function __construct(SystemConfigService $systemConfigService)
  {
    $this->systemConfigService = $systemConfigService;

    $this->environmentSuffix = $this->determineEnvironmentSuffix();
  }

  private function appendEnvironmentSuffix(string $configName): string
  {
      return $configName . $this->environmentSuffix;
  }
  private function determineEnvironmentSuffix(): string
  {
      $mode = $this->systemConfigService->get(self::CONFIG_PATH_PREFIX . 'mode');

      return $mode === 'live' ? self::ENV_PROD_SUFFIX : self::ENV_SANDBOX_SUFFIX;
  }

  public function getConfig(string $configName, ?string $salesChannelId = null): mixed
  {
      $configNameWithEnv = $this->appendEnvironmentSuffix(trim($configName));
      return $this->systemConfigService->get(self::CONFIG_PATH_PREFIX . $configNameWithEnv, $salesChannelId);
  }
}