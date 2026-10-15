<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext\StorefrontContextLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StorefrontContextLoader::class)]
class StorefrontContextLoaderTest extends TestCase
{
    public function testLoadsOnlyTheApprovedContextValuesAndDisablesCaching(): void
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('USD');
        $currency->setSymbol('$');
        $context = Generator::generateSalesChannelContext(
            currency: $currency,
            languageInfo: new LanguageInfo('English', 'en-US'),
        );

        $result = (new StorefrontContextLoader())->load(
            new LoaderInputs([]),
            StorefrontContextLoader::requirement(),
            $context,
            new Request(),
        );

        static::assertSame([
            'currency.isoCode' => 'USD',
            'currency.symbol' => '$',
            'language.localeCode' => 'en-US',
            'tax.state' => $context->getTaxState(),
        ], $result->data?->getVars());
        static::assertFalse($result->isCacheAware());
        static::assertSame(StorefrontContextLoader::SOURCE, StorefrontContextLoader::requirement()->source);
    }
}
