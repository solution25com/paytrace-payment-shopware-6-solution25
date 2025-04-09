<?php

declare(strict_types=1);

namespace PayTrace\Library\Constants;

enum TransactionStatuses: string
{
    case PENDING = 'Pending';
    case PAID = 'Paid';
    case FAIL = "Fail";
    case REFUND = "Refund";
    case CANCELLED = "Cancelled";
    case AUTHORIZED = "Authorized";
    case UNCONFIRMED = "Unconfirmed";
}
