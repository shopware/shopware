<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternTwigExtension;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(VatIdPatternTwigExtension::class)]
class VatIdPatternTwigExtensionTest extends TestCase
{
    public function testRendersTheEuPatternsAsAList(): void
    {
        $provider = static::createStub(VatIdPatternProvider::class);
        $provider->method('getEuPatterns')->willReturn(['AT' => 'ATU\d{8}', 'DE' => 'DE\d{9}']);

        $twig = new Environment(new ArrayLoader(['template' => '{{ sw_eu_vat_id_patterns()|json_encode|raw }}']));
        $twig->addExtension(new VatIdPatternTwigExtension($provider));

        static::assertSame('["ATU\\\d{8}","DE\\\d{9}"]', $twig->render('template'));
    }
}
