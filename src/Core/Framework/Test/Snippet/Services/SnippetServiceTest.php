<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Snippet\Files\LanguageFileCollection;
use Shopware\Core\Framework\Snippet\Services\SnippetFlattener;
use Shopware\Core\Framework\Snippet\Services\SnippetService;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

class SnippetServiceTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @param MessageCatalogueInterface $catalog
     * @param Context                   $context
     * @param array                     $expectedResult
     *
     * @dataProvider dataProviderForTestGetSnippets
     */
    public function testGetStoreFrontSnippets(MessageCatalogueInterface $catalog, Context $context, array $expectedResult): void
    {
        $service = $this->getSnippetService();
        $result = $service->getStorefrontSnippets($catalog, $context);

        $this->assertArraySubset($expectedResult, $result);
        $this->assertNotEmpty($result);
        $this->assertTrue(count($expectedResult) < count($result));
    }

    public function testGetLocaleBySnippetSetId(): void
    {
        $service = $this->getSnippetService();

        $method = ReflectionHelper::getMethod(SnippetService::class, 'getLocaleBySnippetSetId');
        $result_en_GB = $method->invoke($service, Defaults::SNIPPET_BASE_SET_EN);
        $result_de_DE = $method->invoke($service, Defaults::SNIPPET_BASE_SET_DE);

        $this->assertSame(Defaults::LOCALE_EN_GB_ISO, $result_en_GB);
        $this->assertSame(Defaults::LOCALE_DE_DE_ISO, $result_de_DE);
    }

    public function testGetDefaultLocale_expect_en_GB(): void
    {
        $method = ReflectionHelper::getMethod(SnippetService::class, 'getDefaultLocale');
        $result = $method->invoke($this->getSnippetService());

        $this->assertSame(Defaults::LOCALE_EN_GB_ISO, $result);
    }

    public function dataProviderForTestGetSnippets(): array
    {
        $context = $this->getContext(Defaults::SALES_CHANNEL);

        return [
            [$this->getCatalog([], 'en_GB'), $context, []],
            [$this->getCatalog(['messages' => ['a' => 'a']], 'en_GB'), $context, ['a' => 'a']],
            [$this->getCatalog(['messages' => ['a' => 'a', 'b' => 'b']], 'en_GB'), $context, ['a' => 'a', 'b' => 'b']],
        ];
    }

    private function getSnippetService(): SnippetService
    {
        return new SnippetService(
            $this->getContainer()->get('Doctrine\DBAL\Connection'),
            $this->getContainer()->get(SnippetFlattener::class),
            $this->getContainer()->get(LanguageFileCollection::class),
            $this->getContainer()->get('snippet.repository')
        );
    }

    private function getCatalog(array $messages, string $local): MessageCatalogueInterface
    {
        return new MessageCatalogue($local, $messages);
    }

    private function getContext(string $salesChannelId): Context
    {
        $sourceContext = new SourceContext();
        $sourceContext->setSalesChannelId($salesChannelId);

        $context = Context::createDefaultContext();
        $property = ReflectionHelper::getProperty(Context::class, 'sourceContext');
        $property->setValue($context, $sourceContext);

        return $context;
    }
}
