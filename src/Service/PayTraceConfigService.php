<?php

namespace solu1Paytrace\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class PayTraceConfigService
{
    private SystemConfigService $systemConfigService;
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }
    public function getConfig(string $configName, ?string $salesChannelId = null): mixed
    {
        return $this->systemConfigService->get('solu1Paytrace.config.' . trim($configName), $salesChannelId);
    }
    public function getMode(?string $mode): string
    {
        return $mode === 'live' ? 'live' : 'sandbox';
    }
}
