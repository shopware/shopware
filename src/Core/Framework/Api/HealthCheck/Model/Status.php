<?php

namespace Shopware\Core\Framework\Api\HealthCheck\Model;

enum Status
{
    case Healthy;

    case Error;

    case Warning;

    case Deprecation;

    case SKIPPED;
}
