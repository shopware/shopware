<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Translation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Translation\Translator;
use Shopware\Core\PlatformRequest;
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
            $this->translator->getCatalogue('en_GB')->get('frontend.index.footer.IndexCopyright')
        );
    }

    public function testSimpleOverwrite(): void
    {
        $context = Context::createDefaultContext();

        $snippetSetId = Uuid::uuid4();
        $snippetSet = [
            'id' => $snippetSetId->getHex(),
            'name' => 'test123',
            'baseFile' => 'messages.de_DE',
            'iso' => 'de_DE',
        ];
        $this->snippetSetRepository->create([$snippetSet], $context);

        $snippet = [
            'translationKey' => 'frontend.index.footer.IndexCopyright',
            'value' => 'Realisiert mit Unit test',
            'languageId' => Defaults::LANGUAGE_EN,
            'setId' => $snippetSetId->getHex(),
        ];
        $this->snippetRepository->create([$snippet], $context);

        // fake request
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
        $this->getContainer()->get(RequestStack::class)->push($request);

        // get overwritten string
        static::assertEquals(
            $snippet['value'],
            $this->translator->getCatalogue('en_GB')->get('frontend.index.footer.IndexCopyright')
        );
        static::assertSame(
            $request,
            $this->getContainer()->get(RequestStack::class)->pop()
        );
    }

    public function testLanguageInheritance(): void
    {
        $id = Uuid::uuid4();
        $context = Context::createDefaultContext();

        $language = [
            'id' => $id->getHex(),
            'parentId' => Defaults::LANGUAGE_EN,
            'name' => 'Unit language',
        ];
        $this->languageRepository->create([$language], $context);

        $snippetSetId = Uuid::uuid4();
        $snippetsSet = [
            'id' => $snippetSetId->getHex(),
            'name' => 'test123',
            'baseFile' => 'messages.de_DE',
            'iso' => 'de_DE',
        ];
        $this->snippetSetRepository->create([$snippetsSet], $context);

        $snippets = [
            [
                'translationKey' => 'frontend.index.footer.IndexCopyright',
                'value' => 'Realisiert mit Unit test',
                'languageId' => $id->getHex(),
                'setId' => $snippetSetId->getHex(),
            ],
            [
                'translationKey' => 'frontend.index.footer.IndexCopyright',
                'value' => 'Realisiert with default language',
                'languageId' => Defaults::LANGUAGE_EN,
                'setId' => $snippetSetId->getHex(),
            ],
        ];
        $this->snippetRepository->create($snippets, $context);

        /**
         * Simple language overwrite
         */

        // fake request
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
        $this->getContainer()->get(RequestStack::class)->push($request);

        // get default snippet because of default context
        static::assertEquals(
            'Realisiert with default language',
            $this->translator->getCatalogue('en_GB')->get('frontend.index.footer.IndexCopyright')
        );

        // remove old faked request
        $oldRequest = $this->getContainer()->get(RequestStack::class)->pop();
        static::assertSame($request, $oldRequest);

        /**
         * Inherited language overwrite
         */

        // change language in context
        $context = new Context(
            $context->getSourceContext(),
            $context->getCatalogIds(),
            [],
            $context->getCurrencyId(),
            $id->getHex(),
            Defaults::LANGUAGE_EN
        );

        // fake new request
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
        $this->getContainer()->get(RequestStack::class)->push($request);

        // get overwritten string because changed languages
        static::assertEquals(
            'Realisiert mit Unit test',
            $this->translator->getCatalogue('en_GB')->get('frontend.index.footer.IndexCopyright')
        );

        // remove old faked request
        $oldRequest = $this->getContainer()->get(RequestStack::class)->pop();
        static::assertSame($request, $oldRequest);

        /**
         * Fallback to hard-coded snippets
         */
        // change language in context to unknown language
        $context = new Context(
            $context->getSourceContext(),
            $context->getCatalogIds(),
            [],
            $context->getCurrencyId(),
            Uuid::uuid4()->getHex()
        );

        // fake new request
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
        $this->getContainer()->get(RequestStack::class)->push($request);

        // get overwritten string because changed languages
        static::assertEquals(
            'Realized with Shopware',
            $this->translator->getCatalogue('en_GB')->get('frontend.index.footer.IndexCopyright')
        );

        $this->getContainer()->get(RequestStack::class)->pop();
    }
}
