<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Symfony\Component\HttpFoundation\RequestStack;

class RequestStackHelper
{
    public static function clear(RequestStack $stack): void
    {
        do {
            $stack->pop();
        } while ($stack->getMasterRequest());
    }
}
