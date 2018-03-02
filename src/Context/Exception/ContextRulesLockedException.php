<?php

namespace Shopware\Context\Exception;

use Throwable;

class ContextRulesLockedException extends \RuntimeException
{
    public const CODE = 200001;

    public function __construct(Throwable $previous = null)
    {
        parent::__construct('Context rules in shop context already locked.', self::CODE, $previous);
    }
}