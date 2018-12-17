<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Translation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Snippet\Files\LanguageFileInterface;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Translation\Translator;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

class TranslatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var RepositoryInterface
     */
    private $snippetRepository;

    /**
     * @var RepositoryInterface
     */
    private $languageRepository;

    /**
     * @var RepositoryInterface
     */
    private $snippetSetRepository;

    protected function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->translator = $this->getContainer()->get(Translator::class);
        $this->snippetRepository = $this->getContainer()->get('snippet.repository');
        $this->languageRepository = $this->getContainer()->get('language.repository');
        $this->snippetSetRepository = $this->getContainer()->get('snippet_set.repository');

        $this->translator->resetInMemoryCache();
    }

    public function testPassthru(): void
    {
        static::assertEquals(
            'Realized with Shopware',
            $this->translator->getCatalogue('en_GB')->get('footer.copyright')
        );
    }

    public function testSimpleOverwrite(): void
    {
        $context = Context::createDefaultContext();

        $snippet = [
            'translationKey' => 'footer.copyright',
            'value' => 'Realisiert mit Unit test',
            'languageId' => Defaults::LANGUAGE_EN,
            'setId' => Defaults::SNIPPET_BASE_SET_EN,
        ];
        $this->snippetRepository->create([$snippet], $context);

        // fake request
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
        $this->getContainer()->get(RequestStack::class)->push($request);

        // get overwritten string
        static::assertEquals(
            $snippet['value'],
            $this->translator->getCatalogue('en_GB')->get('footer.copyright')
        );
        static::assertSame(
            $request,
            $this->getContainer()->get(RequestStack::class)->pop()
        );
    }

    public function testGetLocaleBySnippetSetId()
    {
        $method = ReflectionHelper::getMethod(Translator::class, 'getLocaleBySnippetSetId');
        $result_en_GB = $method->invoke($this->translator, Defaults::SNIPPET_BASE_SET_EN);
        $result_de_DE = $method->invoke($this->translator, Defaults::SNIPPET_BASE_SET_DE);

        $this->assertSame(Defaults::LOCALE_EN_GB_ISO, $result_en_GB);
        $this->assertSame(Defaults::LOCALE_DE_DE_ISO, $result_de_DE);
    }

    public function testGetDefaultLocale_expect_en_GB(): void
    {
        $method = ReflectionHelper::getMethod(Translator::class, 'getDefaultLocale');
        $result = $method->invoke($this->translator);

        $this->assertSame(Defaults::LOCALE_EN_GB_ISO, $result);
    }

    /**
     * @param MessageCatalogueInterface $catalog
     * @param Context                   $context
     * @param array                     $expectedResult
     *
     * @dataProvider dataProviderForTestGetSnippets
     */
    public function testGetSnippets(MessageCatalogueInterface $catalog, Context $context, array $expectedResult): void
    {
        $method = ReflectionHelper::getMethod(Translator::class, 'getSnippets');
        $result = $method->invokeArgs($this->translator, [$catalog, $context]);

        $this->assertArraySubset($expectedResult, $result);
        $this->assertNotEmpty($result);
        $this->assertTrue(count($expectedResult) < count($result));
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

    private function getCatalog(array $messages, string $local): MessageCatalogueInterface
    {
        return new MessageCatalogue($local, $messages);
    }

    private function getContext(string $salesChannelId)
    {
        $sourceContext = new SourceContext();
        $sourceContext->setSalesChannelId($salesChannelId);

        $context = Context::createDefaultContext();
        $property = ReflectionHelper::getProperty(Context::class, 'sourceContext');
        $property->setValue($context, $sourceContext);

        return $context;
    }
}

class LanguageFileMock implements LanguageFileInterface
{
    public $path;
    public $name;
    public $iso;
    public $isBase;

    public function __construct(
        string $path = __DIR__ . '/_fixtures/test_three.json',
        string $name = 'LanguageFileMock',
        string $iso = 'en_GB',
        bool $isBase = true
    ) {
        $this->path = $path;
        $this->name = $name;
        $this->iso = $iso;
        $this->isBase = $isBase;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getIso(): string
    {
        return $this->iso;
    }

    public function isBase(): bool
    {
        return $this->isBase;
    }
}
