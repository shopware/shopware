<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;

/**
 * @internal
 */
#[Package('discovery')]
class ThemeLifecycleHandlerInheritanceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<ThemeCollection>
     */
    private EntityRepository $themeRepository;

    private ThemeLifecycleHandler $handler;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->themeRepository = static::getContainer()->get('theme.repository');
        $this->handler = static::getContainer()->get(ThemeLifecycleHandler::class);
    }

    /**
     * @return \Generator<string, array{string, int}>
     */
    public static function lifecycleAssignmentsProvider(): \Generator
    {
        yield 'deactivation names the grandchild copy and its sales channel' => ['deactivate', 1];
        yield 'uninstall names the grandchild copy and its sales channel' => ['uninstall', 1];
        yield 'deactivation lists both sales channels of the grandchild copy once' => ['deactivate', 2];
        yield 'uninstall lists both sales channels of the grandchild copy once' => ['uninstall', 2];
    }

    #[DataProvider('lifecycleAssignmentsProvider')]
    public function testAssignedGrandchildCopyPreventsRemovingBaseTheme(string $operation, int $salesChannelCount): void
    {
        $base = $this->createTheme(name: 'Lifecycle Base', technicalName: 'LifecycleBase');
        $parent = $this->createTheme(name: 'Lifecycle Parent', technicalName: 'LifecycleParent', parentId: $base);
        $copy = $this->createTheme(name: 'Grandchild Copy', parentId: $parent);
        $salesChannels = [];
        for ($index = 0; $index < $salesChannelCount; ++$index) {
            $name = $index === 0 ? 'Storefront DE' : 'Storefront EN';
            $salesChannels[$this->createSalesChannel($copy, $name)] = $name;
        }

        $config = new StorefrontPluginConfiguration('LifecycleBase');
        $config->setIsTheme(true);
        $this->expectExceptionObject(ThemeException::themeAssignmentException(
            'Lifecycle Base',
            [],
            ['Grandchild Copy' => array_keys($salesChannels)],
            $salesChannels,
        ));

        try {
            if ($operation === 'deactivate') {
                $this->handler->deactivateTheme($config, $this->context);
            } else {
                $this->handler->handleThemeUninstall($config, $this->context);
            }
        } finally {
            $themes = $this->themeRepository->search(new Criteria([$base, $parent, $copy]), $this->context)->getEntities();
            static::assertCount(3, $themes);
            foreach ($themes as $theme) {
                static::assertTrue($theme->isActive());
            }
        }
    }

    private function createTheme(string $name, ?string $technicalName = null, ?string $parentId = null): string
    {
        $id = Uuid::randomHex();
        $this->themeRepository->create([[
            'id' => $id,
            'name' => $name,
            'technicalName' => $technicalName,
            'parentThemeId' => $parentId,
            'author' => 'Shopware',
            'active' => true,
        ]], $this->context);

        return $id;
    }

    private function createSalesChannel(string $themeId, string $name): string
    {
        $repository = static::getContainer()->get('sales_channel.repository');
        $template = $repository->search(new Criteria([TestDefaults::SALES_CHANNEL]), $this->context)->getEntities()->first();
        static::assertInstanceOf(SalesChannelEntity::class, $template);
        $id = Uuid::randomHex();
        $repository->create([[
            'id' => $id,
            'name' => $name,
            'accessKey' => Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'currencies' => [['id' => Defaults::CURRENCY]],
            'paymentMethodId' => $template->getPaymentMethodId(),
            'shippingMethodId' => $template->getShippingMethodId(),
            'navigationCategoryId' => $template->getNavigationCategoryId(),
            'countryId' => $template->getCountryId(),
            'customerGroupId' => $template->getCustomerGroupId(),
        ]], $this->context);
        static::getContainer()->get('theme_sales_channel.repository')->upsert([[
            'themeId' => $themeId,
            'salesChannelId' => $id,
        ]], $this->context);

        return $id;
    }
}
