<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Translation;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Translation\Translator;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class TranslatorTest extends KernelTestCase
{
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

    protected function setUp()
    {
        self::bootKernel();

        $this->connection = self::$container->get(Connection::class);
        $this->translator = self::$container->get(Translator::class);
        $this->snippetRepository = self::$container->get('snippet.repository');
        $this->languageRepository = self::$container->get('language.repository');

        $this->connection->beginTransaction();
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testPassthru(): void
    {
        $this->assertEquals(
            'Realized with Shopware',
            $this->translator->getCatalogue('en_GB')->get('frontend.index.footer.IndexCopyright')
        );
    }

    public function testSimpleOverwrite(): void
    {
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $snippet = [
            'translationKey' => 'frontend.index.footer.IndexCopyright',
            'value' => 'Realisiert mit Unit test',
            'languageId' => Defaults::LANGUAGE,
        ];

        $this->snippetRepository->create([$snippet], $context);

        // fake request
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
        self::$container->get(RequestStack::class)->push($request);

        // get overwritten string
        $this->assertEquals(
            $snippet['value'],
            $this->translator->getCatalogue('en_GB')->get('frontend.index.footer.IndexCopyright')
        );
    }

    public function testLanguageInheritance(): void
    {
        $id = Uuid::uuid4();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $language = [
            'id' => $id->getHex(),
            'parentId' => Defaults::LANGUAGE,
            'name' => 'Unit language',
            'localeId' => Defaults::LOCALE,
        ];

        $this->languageRepository->create([$language], $context);

        $snippets = [
            [
                'translationKey' => 'frontend.index.footer.IndexCopyright',
                'value' => 'Realisiert mit Unit test',
                'languageId' => $id->getHex(),
            ],
            [
                'translationKey' => 'frontend.index.footer.IndexCopyright',
                'value' => 'Realisiert with default language',
                'languageId' => Defaults::LANGUAGE,
            ],
        ];

        $this->snippetRepository->create($snippets, $context);

        /**
         * Simple language overwrite
         */

        // fake request
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
        self::$container->get(RequestStack::class)->push($request);

        // get default snippet because of default context
        $this->assertEquals(
            $snippets[1]['value'],
            $this->translator->getCatalogue('en_GB')->get('frontend.index.footer.IndexCopyright')
        );

        // remove old faked request
        $oldRequest = self::$container->get(RequestStack::class)->pop();
        $this->assertSame($request, $oldRequest);

        /**
         * Inherited language overwrite
         */

        // change language in context
        $context = new Context(
            $context->getTenantId(),
            $context->getSourceContext(),
            $context->getCatalogIds(),
            [],
            $context->getCurrencyId(),
            $id->getHex(),
            Defaults::LANGUAGE
        );

        // fake new request
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
        self::$container->get(RequestStack::class)->push($request);

        // get overwritten string because changed languages
        $this->assertEquals(
            $snippets[0]['value'],
            $this->translator->getCatalogue('en_GB')->get('frontend.index.footer.IndexCopyright')
        );

        // remove old faked request
        $oldRequest = self::$container->get(RequestStack::class)->pop();
        $this->assertSame($request, $oldRequest);

        /**
         * Fallback to hard-coded snippets
         */
        // change language in context to unknown language
        $context = new Context(
            $context->getTenantId(),
            $context->getSourceContext(),
            $context->getCatalogIds(),
            [],
            $context->getCurrencyId(),
            Uuid::uuid4()->getHex()
        );

        // fake new request
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
        self::$container->get(RequestStack::class)->push($request);

        // get overwritten string because changed languages
        $this->assertEquals(
            'Realized with Shopware',
            $this->translator->getCatalogue('en_GB')->get('frontend.index.footer.IndexCopyright')
        );
    }
}
