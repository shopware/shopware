<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Translation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
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
            $this->translator->getCatalogue('en_GB')->get('footer.copyright')
        );
    }

    public function testSimpleOverwrite(): void
    {
        $context = Context::createDefaultContext();

        $snippet = [
            'translationKey' => 'footer.copyright',
            'value' => 'Realisiert mit Unit test',
            'languageId' => Defaults::LANGUAGE_SYSTEM,
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
}
