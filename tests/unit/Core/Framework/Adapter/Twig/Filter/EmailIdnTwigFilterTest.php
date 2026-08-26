<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Framework\Adapter\Twig\Filter\EmailIdnTwigFilter;
use Shopware\Core\Framework\Log\Package;

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

        $decode = $filter->getFilters()[0];
        static::assertSame('decodeIdnEmail', $decode->getName());
        $decodeCallable = $decode->getCallable();
        static::assertIsCallable($decodeCallable);
        static::assertSame(EmailIdnConverter::decode('test@xn--mller-kva.de'), $decodeCallable('test@xn--mller-kva.de'));

        $encode = $filter->getFilters()[1];
        static::assertSame('encodeIdnEmail', $encode->getName());
        $encodeCallable = $encode->getCallable();
        static::assertIsCallable($encodeCallable);
        static::assertSame(EmailIdnConverter::encode('test@müller.de'), $encodeCallable('test@müller.de'));
    }
}
