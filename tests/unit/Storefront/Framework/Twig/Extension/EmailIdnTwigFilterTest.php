<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Twig\Extension\EmailIdnTwigFilter;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(EmailIdnTwigFilter::class)]
class EmailIdnTwigFilterTest extends TestCase
{
    public function testIdnFilter(): void
    {
        $filter = new EmailIdnTwigFilter();

        static::assertCount(2, $filter->getFilters());

        static::assertSame($filter->getFilters()[0]->getName(), 'decodeIdnEmail');
        static::assertSame([EmailIdnConverter::class, 'decode'], $filter->getFilters()[0]->getCallable());

        static::assertSame($filter->getFilters()[1]->getName(), 'encodeIdnEmail');
        static::assertSame([EmailIdnConverter::class, 'encode'], $filter->getFilters()[1]->getCallable());
    }
}
