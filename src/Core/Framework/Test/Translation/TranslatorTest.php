<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Translation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Snippet\SnippetDefinition;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Translation\Translator;
use Shopware\Storefront\StorefrontRequest;
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

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->translator = $this->getContainer()->get(Translator::class);
        $this->snippetRepository = $this->getContainer()->get('snippet.repository');
        $this->languageRepository = $this->getContainer()->get('language.repository');

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
            'translationKey' => 'new.unit.test.key',
            'value' => 'Realisiert mit Unit test',
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'setId' => Defaults::SNIPPET_BASE_SET_EN,
            'author' => Defaults::SNIPPET_AUTHOR,
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
            'id' => Uuid::uuid4()->getHex(),
            'translationKey' => 'foo',
            'value' => 'bar',
            'setId' => Defaults::SNIPPET_BASE_SET_EN,
            'author' => Defaults::SNIPPET_AUTHOR,
        ];

        $created = $snippetRepository->create([$snippet], Context::createDefaultContext())->getEventByDefinition(SnippetDefinition::class);
        static::assertEquals([$snippet['id']], $created->getIds());

        $deleted = $snippetRepository->delete([['id' => $snippet['id']]], Context::createDefaultContext())->getEventByDefinition(SnippetDefinition::class);
        static::assertEquals([$snippet['id']], $deleted->getIds());
    }
}
