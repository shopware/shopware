<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Translation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Snippet\Files\SnippetFileCollection;
use Shopware\Core\Framework\Snippet\SnippetDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Test\Translation\_fixtures\SnippetFile_UnitTest;
use Shopware\Core\Framework\Translation\Translator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\StorefrontRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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
     * @var EntityRepositoryInterface
     */
    private $snippetRepository;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->translator = $this->getContainer()->get(Translator::class);
        $this->snippetRepository = $this->getContainer()->get('snippet.repository');

        $this->translator->resetInMemoryCache();
    }

    public function testPassthru(): void
    {
        $snippetFile = new SnippetFile_UnitTest();
        $this->getContainer()->get(SnippetFileCollection::class)->add($snippetFile);

        $stack = $this->getContainer()->get(RequestStack::class);
        $prop = ReflectionHelper::getProperty(RequestStack::class, 'requests');
        $prop->setValue($stack, []);

        // fake request
        $request = new Request();
        $request->attributes->set(StorefrontRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, Defaults::SNIPPET_BASE_SET_EN);
        $request->attributes->set(StorefrontRequest::ATTRIBUTE_DOMAIN_LOCALE, Defaults::LOCALE_EN_GB_ISO);

        $stack->push($request);
        $result = $this->translator->getCatalogue('en_GB')->get('frontend.note.item.NoteLinkZoom');
        $prop->setValue($stack, []);

        static::assertEquals(
            'Enlarge',
            $result
        );
    }

    public function testSimpleOverwrite(): void
    {
        $context = Context::createDefaultContext();

        $snippet = [
            'translationKey' => 'new.unit.test.key',
            'value' => 'Realisiert mit Unit test',
            'setId' => Defaults::SNIPPET_BASE_SET_EN,
            'author' => 'Shopware',
        ];
        $this->snippetRepository->create([$snippet], $context);

        // fake request
        $request = new Request();

        $request->attributes->set(StorefrontRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, Defaults::SNIPPET_BASE_SET_EN);
        $request->attributes->set(StorefrontRequest::ATTRIBUTE_DOMAIN_LOCALE, Defaults::LOCALE_EN_GB_ISO);

        $this->getContainer()->get(RequestStack::class)->push($request);

        // get overwritten string
        static::assertEquals(
            $snippet['value'],
            $this->translator->getCatalogue('en_GB')->get('new.unit.test.key')
        );
        static::assertSame(
            $request,
            $this->getContainer()->get(RequestStack::class)->pop()
        );
    }

    public function testDeleteSnippet(): void
    {
        $snippetRepository = $this->getContainer()->get('snippet.repository');
        $snippet = [
            'id' => Uuid::randomHex(),
            'translationKey' => 'foo',
            'value' => 'bar',
            'setId' => Defaults::SNIPPET_BASE_SET_EN,
            'author' => 'Shopware',
        ];

        $created = $snippetRepository->create([$snippet], Context::createDefaultContext())->getEventByDefinition(SnippetDefinition::class);
        static::assertEquals([$snippet['id']], $created->getIds());

        $deleted = $snippetRepository->delete([['id' => $snippet['id']]], Context::createDefaultContext())->getEventByDefinition(SnippetDefinition::class);
        static::assertEquals([$snippet['id']], $deleted->getIds());
    }
}
