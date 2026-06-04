<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme\Mail;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Mail\MailThemeIdLoader;

/**
 * @internal
 */
class MailThemeIdLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testLoadsThemeIdForSalesChannel(): void
    {
        $themeId = $this->getThemeId();

        $salesChannel = $this->createSalesChannelWithUniqueDomain([
            'themes' => [
                [
                    'id' => $themeId,
                ],
            ],
        ]);

        static::assertSame($themeId, static::getContainer()->get(MailThemeIdLoader::class)->load($salesChannel['id']));
    }

    public function testReturnsNullWhenSalesChannelHasNoTheme(): void
    {
        $salesChannel = $this->createSalesChannelWithUniqueDomain();

        static::assertNull(static::getContainer()->get(MailThemeIdLoader::class)->load($salesChannel['id']));
    }

    /**
     * @param array<string, mixed> $salesChannelOverride
     *
     * @return array<string, mixed>
     */
    private function createSalesChannelWithUniqueDomain(array $salesChannelOverride = []): array
    {
        return $this->createSalesChannel(array_replace_recursive([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost/' . Uuid::randomHex(),
                ],
            ],
        ], $salesChannelOverride));
    }

    private function getThemeId(): string
    {
        $id = static::getContainer()->get('theme.repository')->searchIds(new Criteria(), Context::createDefaultContext())->firstId();

        static::assertIsString($id);

        return $id;
    }
}
