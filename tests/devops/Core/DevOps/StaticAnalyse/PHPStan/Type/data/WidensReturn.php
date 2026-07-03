<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeWidening;

use function PHPStan\Testing\assertType;

class WidensReturn
{
    #[ReturnTypeWidening(version: 'v6.8.0', newType: '?string')]
    public function getUrl(): string
    {
        return 'https://example.com';
    }

    public function unchanged(): string
    {
        return 'stable';
    }
}

function (WidensReturn $subject): void {
    assertType('string|null', $subject->getUrl());
    assertType('string', $subject->unchanged());
};
