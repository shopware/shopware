<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Filter\SnippetFilterFactory;
use Shopware\Core\System\Snippet\SnippetService;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Tests\Unit\Core\System\Snippet\Mock\MockSnippetFile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\Snippet\SnippetService
 */
class SnippetServiceTest extends TestCase
{
    private SnippetFileCollection $snippetCollection;

    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->snippetCollection = new SnippetFileCollection();
    }

    public function testGetStorefrontSnippets(): void
    {
        $themeRegistry = class_exists(StorefrontPluginRegistry::class) ? $this->createMock(StorefrontPluginRegistry::class) : null;

        if ($themeRegistry === null) {
            $this->testGetStorefrontSnippetsWithoutThemeRegistry();

            return;
        }

        $this->addThemes();

        $locale = 'de-DE';
        $snippetSetId = Uuid::randomHex();
        $catalog = new MessageCatalogue($locale, []);

        $requestStack = new RequestStack();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(static::once())->method('has')->with(StorefrontPluginRegistry::class)->willReturn(true);
        $container->expects(static::once())->method('get')->with(StorefrontPluginRegistry::class)->willReturn($themeRegistry);

        $snippetService = new SnippetService(
            $this->connection,
            $this->snippetCollection,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(SnippetFilterFactory::class),
            $requestStack,
            $container
        );

        $plugins = new StorefrontPluginConfigurationCollection();

        $storefront = new StorefrontPluginConfiguration('Storefront');
        $storefront->setIsTheme(true);
        $swagTheme = new StorefrontPluginConfiguration('SwagTheme');
        $swagTheme->setIsTheme(true);

        $plugins->add($storefront);
        $plugins->add($swagTheme);

        $themeRegistry->expects(static::once())->method('getConfigurations')->willReturn($plugins);

        $snippets = $snippetService->getStorefrontSnippets($catalog, $snippetSetId, $locale);

        static::assertSame([
            'title' => 'Storefront DE',
        ], $snippets);
    }

    public function testGetStorefrontSnippetsWithoutThemeRegistry(): void
    {
        $this->addThemes();

        $locale = 'de-DE';
        $snippetSetId = Uuid::randomHex();
        $catalog = new MessageCatalogue($locale, []);

        $requestStack = new RequestStack();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects(static::once())->method('has')->with(StorefrontPluginRegistry::class)->willReturn(false);

        $snippetService = new SnippetService(
            $this->connection,
            $this->snippetCollection,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(SnippetFilterFactory::class),
            $requestStack,
            $container
        );

        $snippets = $snippetService->getStorefrontSnippets($catalog, $snippetSetId, $locale);

        static::assertSame([
            'title' => 'SwagTheme DE',
        ], $snippets);
    }

    private function addThemes(): void
    {
        $this->snippetCollection->add(new MockSnippetFile('storefront.de-DE', 'de-DE', '{}', true, 'Storefront'));
        $this->snippetCollection->add(new MockSnippetFile('storefront.en-GB', 'en-GB', '{}', true, 'Storefront'));
        $this->snippetCollection->add(new MockSnippetFile('swagtheme.de-DE', 'de-DE', '{}', true, 'SwagTheme'));
        $this->snippetCollection->add(new MockSnippetFile('swagtheme.en-GB', 'en-GB', '{}', true, 'SwagTheme'));
    }
}
