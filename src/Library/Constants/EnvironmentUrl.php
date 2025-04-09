<?php

declare(strict_types=1);

namespace PayTrace\Library\Constants;

enum EnvironmentUrl: string
{
    case SANDBOX = 'https://api.paytrace.com';
    case PRODUCTION = 'https://api.sandbox.paytrace.com';
}
