<?php

namespace PayTrace\Service;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class PayTraceConfigService
{

  private SystemConfigService $systemConfigService;
  public function __construct(SystemConfigService $systemConfigService)
  {
    $this->systemConfigService = $systemConfigService;
  }
  public function getConfig(string $configName): mixed
  {
    return $this->systemConfigService->get('PayTrace.config.' . trim($configName));
  }

}