<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Symfony\Component\HttpFoundation\RequestStack;

class RequestStackHelper
{
    public static function clear(RequestStack $stack): array
    {
        $requests = [];

        while ($stack->getMasterRequest()) {
            $requests[] = $stack->pop();
        }

        return $requests;
    }
}
