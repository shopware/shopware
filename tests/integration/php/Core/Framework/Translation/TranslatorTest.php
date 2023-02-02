<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Translation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Tests\Integration\Core\Framework\Translation\Fixtures\SnippetFile_UnitTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * @internal
 */
class TranslatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private Translator $translator;

    private EntityRepository $snippetRepository;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->translator = $this->getContainer()->get(Translator::class);
        $this->snippetRepository = $this->getContainer()->get('snippet.repository');

        $this->translator->resetInMemoryCache();
        $this->translator->warmUp('');
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
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $this->getSnippetSetIdForLocale('en-GB'));
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, 'en-GB');

        $stack->push($request);
        $result = $this->translator->getCatalogue('en-GB')->get('frontend.note.item.NoteLinkZoom');
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
            'setId' => $this->getSnippetSetIdForLocale('en-GB'),
            'author' => 'Shopware',
        ];
        $this->snippetRepository->create([$snippet], $context);

        // fake request
        $request = new Request();

        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $this->getSnippetSetIdForLocale('en-GB'));
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, 'en-GB');

        $this->getContainer()->get(RequestStack::class)->push($request);

        // get overwritten string
        static::assertEquals(
            $snippet['value'],
            $this->translator->getCatalogue('en-GB')->get('new.unit.test.key')
        );
        static::assertSame(
            $request,
            $this->getContainer()->get(RequestStack::class)->pop()
        );
    }

    public function testSymfonyDefaultTranslationFallback(): void
    {
        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('en');
        static::assertInstanceOf(MessageCatalogueInterface::class, $catalogue->getFallbackCatalogue());
        static::assertEquals('en_GB', $catalogue->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('en_GB');
        static::assertInstanceOf(MessageCatalogueInterface::class, $catalogue->getFallbackCatalogue());
        static::assertEquals('en_001', $catalogue->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('en-GB');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback);
        static::assertEquals('en', $fallback->getLocale());
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback->getFallbackCatalogue());
        static::assertEquals('en_GB', $fallback->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('de');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback);
        static::assertEquals('en_GB', $fallback->getLocale());
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback->getFallbackCatalogue());
        static::assertEquals('en', $fallback->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('de_DE');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback);
        static::assertEquals('de', $fallback->getLocale());
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback->getFallbackCatalogue());
        static::assertEquals('en_GB', $fallback->getFallbackCatalogue()->getLocale());
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback->getFallbackCatalogue()->getFallbackCatalogue());
        static::assertEquals('en', $fallback->getFallbackCatalogue()->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('de-DE');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback);
        static::assertEquals('en', $fallback->getLocale());
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback->getFallbackCatalogue());
        static::assertEquals('en_GB', $fallback->getFallbackCatalogue()->getLocale());
        $this->translator->resetInMemoryCache();
    }

    public function testSymfonyDefaultTranslationFallbackWithCustomShopwareDefaultLanguage(): void
    {
        $this->switchDefaultLanguage();

        $catalogue = $this->translator->getCatalogue('en');
        static::assertInstanceOf(MessageCatalogueInterface::class, $catalogue->getFallbackCatalogue());
        static::assertEquals('en_GB', $catalogue->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('en_GB');
        static::assertInstanceOf(MessageCatalogueInterface::class, $catalogue->getFallbackCatalogue());
        static::assertEquals('en_001', $catalogue->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('en-GB');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback);
        static::assertEquals('de', $fallback->getLocale());
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback->getFallbackCatalogue());
        static::assertEquals('en_GB', $fallback->getFallbackCatalogue()->getLocale());
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback->getFallbackCatalogue()->getFallbackCatalogue());
        static::assertEquals('en', $fallback->getFallbackCatalogue()->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('de');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback);
        static::assertEquals('en_GB', $fallback->getLocale());
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback->getFallbackCatalogue());
        static::assertEquals('en', $fallback->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('de_DE');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback);
        static::assertEquals('de', $fallback->getLocale());
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback->getFallbackCatalogue());
        static::assertEquals('en_GB', $fallback->getFallbackCatalogue()->getLocale());
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback->getFallbackCatalogue()->getFallbackCatalogue());
        static::assertEquals('en', $fallback->getFallbackCatalogue()->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('de-DE');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback);
        static::assertEquals('de', $fallback->getLocale());
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback->getFallbackCatalogue());
        static::assertEquals('en_GB', $fallback->getFallbackCatalogue()->getLocale());
        static::assertInstanceOf(MessageCatalogueInterface::class, $fallback->getFallbackCatalogue()->getFallbackCatalogue());
        static::assertEquals('en', $fallback->getFallbackCatalogue()->getFallbackCatalogue()->getLocale());
        $this->translator->resetInMemoryCache();
    }

    public function testTranslatorCustomLocaleAndFallback(): void
    {
        $context = Context::createDefaultContext();

        $snippets = [
            [
                'translationKey' => 'new.unit.test.key',
                'value' => 'Realized with Unit test',
                'setId' => $this->getSnippetSetIdForLocale('en-GB'),
                'author' => 'Shopware',
            ],
            [
                'translationKey' => 'new.unit.test.key',
                'value' => 'Realisiert mit Unit test',
                'setId' => $this->getSnippetSetIdForLocale('de-DE'),
                'author' => 'Shopware',
            ],
        ];
        $this->snippetRepository->create($snippets, $context);

        // fake request
        $request = new Request();

        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $this->getSnippetSetIdForLocale('en-GB'));
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, 'en-GB');

        $this->getContainer()->get(RequestStack::class)->push($request);

        // get overwritten string
        static::assertEquals(
            $snippets[0]['value'],
            $this->translator->trans('new.unit.test.key', [], null, 'en-GB')
        );
        static::assertEquals(
            $snippets[1]['value'],
            $this->translator->trans('new.unit.test.key', [], null, 'de-DE')
        );
        static::assertEquals(
            $snippets[0]['value'],
            $this->translator->trans('new.unit.test.key', [], null, 'en')
        );
        static::assertEquals(
            $snippets[1]['value'],
            $this->translator->trans('new.unit.test.key', [], null, 'de-DE')
        );
        static::assertEquals(
            $snippets[0]['value'],
            $this->translator->trans('new.unit.test.key')
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
            'setId' => $this->getSnippetSetIdForLocale('en-GB'),
            'author' => 'Shopware',
        ];

        $created = $snippetRepository->create([$snippet], Context::createDefaultContext())->getEventByEntityName(SnippetDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $created);
        static::assertEquals([$snippet['id']], $created->getIds());

        $deleted = $snippetRepository->delete([['id' => $snippet['id']]], Context::createDefaultContext())->getEventByEntityName(SnippetDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $deleted);
        static::assertEquals([$snippet['id']], $deleted->getIds());
    }

    public function testItReplacesReservedCharacter(): void
    {
        static::assertEquals('translator.<_r_strong>', Translator::buildName('</strong>'));
    }

    private function switchDefaultLanguage(): void
    {
        $currentDeId = $this->connection->fetchOne(
            'SELECT language.id
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE locale.code = "de-DE"'
        );

        $stmt = $this->connection->prepare(
            'UPDATE language
             SET id = :newId
             WHERE id = :oldId'
        );

        // assign new uuid to old DEFAULT
        $stmt->executeStatement([
            'newId' => Uuid::randomBytes(),
            'oldId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
        ]);

        // change id to DEFAULT
        $stmt->executeStatement([
            'newId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'oldId' => $currentDeId,
        ]);
    }
}
