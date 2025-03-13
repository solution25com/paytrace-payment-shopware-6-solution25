<?php

declare(strict_types=1);

namespace PayTrace\Library\Constants;

enum EnvironmentUrl: string
{
  CASE SANDBOX = 'https://api.paytrace.com';
  CASE PRODUCTION = 'https://api.sandbox.paytrace.com';

}
