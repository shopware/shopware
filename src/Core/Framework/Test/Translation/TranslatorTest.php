<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Translation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Test\Translation\_fixtures\SnippetFile_UnitTest;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\SnippetDefinition;
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
        $this->translator->warmUp('');
        $this->clearInternalTranslatorFallbackLocaleCache();
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
        static::assertEquals('en_GB', $catalogue->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('en_GB');
        static::assertEquals('en', $catalogue->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('en-GB');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertEquals('en', $fallback->getLocale());
        static::assertEquals('en_GB', $fallback->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('de');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertEquals('en_GB', $fallback->getLocale());
        static::assertEquals('en', $fallback->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('de_DE');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertEquals('de', $fallback->getLocale());
        static::assertEquals('en_GB', $fallback->getFallbackCatalogue()->getLocale());
        static::assertEquals('en', $fallback->getFallbackCatalogue()->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('de-DE');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertEquals('en', $fallback->getLocale());
        static::assertEquals('en_GB', $fallback->getFallbackCatalogue()->getLocale());
        $this->translator->resetInMemoryCache();
    }

    public function testSymfonyDefaultTranslationFallbackWithCustomShopwareDefaultLanguage(): void
    {
        $this->switchDefaultLanguage();

        $catalogue = $this->translator->getCatalogue('en');
        static::assertEquals('en_GB', $catalogue->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('en_GB');
        static::assertEquals('en', $catalogue->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('en-GB');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertEquals('de', $fallback->getLocale());
        static::assertEquals('en_GB', $fallback->getFallbackCatalogue()->getLocale());
        static::assertEquals('en', $fallback->getFallbackCatalogue()->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('de');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertEquals('en_GB', $fallback->getLocale());
        static::assertEquals('en', $fallback->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('de_DE');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertEquals('de', $fallback->getLocale());
        static::assertEquals('en_GB', $fallback->getFallbackCatalogue()->getLocale());
        static::assertEquals('en', $fallback->getFallbackCatalogue()->getFallbackCatalogue()->getLocale());

        $this->translator->resetInMemoryCache();
        $catalogue = $this->translator->getCatalogue('de-DE');
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertEquals('de', $fallback->getLocale());
        static::assertEquals('en_GB', $fallback->getFallbackCatalogue()->getLocale());
        static::assertEquals('en', $fallback->getFallbackCatalogue()->getFallbackCatalogue()->getLocale());
        $this->translator->resetInMemoryCache();
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
        static::assertEquals([$snippet['id']], $created->getIds());

        $deleted = $snippetRepository->delete([['id' => $snippet['id']]], Context::createDefaultContext())->getEventByEntityName(SnippetDefinition::ENTITY_NAME);
        static::assertEquals([$snippet['id']], $deleted->getIds());
    }

    private function switchDefaultLanguage(): void
    {
        $currentDeId = $this->connection->fetchColumn(
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
        $stmt->execute([
            'newId' => Uuid::randomBytes(),
            'oldId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
        ]);

        // change id to DEFAULT
        $stmt->execute([
            'newId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'oldId' => $currentDeId,
        ]);
    }

    private function clearInternalTranslatorFallbackLocaleCache(): void
    {
        $reflection = new \ReflectionClass($this->translator);
        $prop = $reflection->getProperty('fallbackLocale');

        $prop->setAccessible(true);
        $prop->setValue($this->translator, null);
    }
}
