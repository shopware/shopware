<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\BackwardCompatibleIntlExtension;
use Shopware\Core\Framework\Log\Package;
use Twig\Extra\Intl\IntlExtension;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BackwardCompatibleIntlExtension::class)]
class BackwardCompatibleIntlExtensionTest extends TestCase
{
    public function testDoesNotRegisterFiltersWhenV6800IsActive(): void
    {
        $extension = new BackwardCompatibleIntlExtension(new IntlExtension());

        static::assertSame([], $extension->getFilters());
    }
}
