<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Administration\Events\PreResetExcludedSearchTermEvent;
use Shopware\Administration\Framework\Routing\KnownIps\KnownIpsCollectorInterface;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AdministrationController extends AbstractController
{
    /**
     * @var TemplateFinder
     */
    private $finder;

    /**
     * @var FirstRunWizardClient
     */
    private $firstRunWizardClient;

    /**
     * @var SnippetFinderInterface
     */
    private $snippetFinder;

    /**
     * @var array
     */
    private $supportedApiVersions;

    /**
     * @var KnownIpsCollectorInterface
     */
    private $knownIpsCollector;

    private Connection $connection;

    private EventDispatcherInterface $eventDispatcher;

    private string $shopwareCoreDir;

    public function __construct(
        TemplateFinder $finder,
        FirstRunWizardClient $firstRunWizardClient,
        SnippetFinderInterface $snippetFinder,
        array $supportedApiVersions,
        KnownIpsCollectorInterface $knownIpsCollector,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        string $shopwareCoreDir
    ) {
        $this->finder = $finder;
        $this->firstRunWizardClient = $firstRunWizardClient;
        $this->snippetFinder = $snippetFinder;
        $this->supportedApiVersions = $supportedApiVersions;
        $this->knownIpsCollector = $knownIpsCollector;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->shopwareCoreDir = $shopwareCoreDir;
    }

    /**
     * @Since("6.3.3.0")
     * @RouteScope(scopes={"administration"})
     * @Route("/admin", defaults={"auth_required"=false}, name="administration.index", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $template = $this->finder->find('@Administration/administration/index.html.twig');

        return $this->render($template, [
            'features' => Feature::getAll(),
            'systemLanguageId' => Defaults::LANGUAGE_SYSTEM,
            'defaultLanguageIds' => [Defaults::LANGUAGE_SYSTEM],
            'systemCurrencyId' => Defaults::CURRENCY,
            'liveVersionId' => Defaults::LIVE_VERSION,
            'firstRunWizard' => $this->firstRunWizardClient->frwShouldRun(),
            'apiVersion' => $this->getLatestApiVersion(),
            'cspNonce' => $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE),
        ]);
    }

    /**
     * @Since("6.1.0.0")
     * @RouteScope(scopes={"administration"})
     * @Route("/api/_admin/snippets", name="api.admin.snippets", methods={"GET"})
     */
    public function snippets(Request $request): Response
    {
        $locale = $request->query->get('locale', 'en-GB');
        $snippets[$locale] = $this->snippetFinder->findSnippets($locale);

        if ($locale !== 'en-GB') {
            $snippets['en-GB'] = $this->snippetFinder->findSnippets('en-GB');
        }

        return new JsonResponse($snippets);
    }

    /**
     * @Since("6.3.1.0")
     * @RouteScope(scopes={"administration"})
     * @Route("/api/_admin/known-ips", name="api.admin.known-ips", methods={"GET"})
     */
    public function knownIps(Request $request): Response
    {
        $ips = [];

        foreach ($this->knownIpsCollector->collectIps($request) as $ip => $name) {
            $ips[] = [
                'name' => $name,
                'value' => $ip,
            ];
        }

        return new JsonResponse(['ips' => $ips]);
    }

    /**
     * @Since("6.4.0.1")
     * @RouteScope(scopes={"administration"})
     * @Route("/api/_admin/reset-excluded-search-term", name="api.admin.reset-excluded-search-term", methods={"POST"})
     * @Acl({"system_config:update", "system_config:create", "system_config:delete"})
     *
     * @throws LanguageNotFoundException|\Doctrine\DBAL\DBALException
     *
     * @return JsonResponse
     */
    public function resetExcludedSearchTerm(Context $context)
    {
        $searchConfigId = $this->connection->fetchColumn('SELECT id FROM product_search_config WHERE language_id = :language_id', ['language_id' => Uuid::fromHexToBytes($context->getLanguageId())]);

        if ($searchConfigId === false) {
            throw new LanguageNotFoundException($context->getLanguageId());
        }

        $deLanguageId = $this->fetchLanguageIdByName('de-DE', $this->connection);
        $enLanguageId = $this->fetchLanguageIdByName('en-GB', $this->connection);

        switch ($context->getLanguageId()) {
            case $deLanguageId:
                $defaultExcludedTerm = require $this->shopwareCoreDir . '/Migration/Fixtures/stopwords/de.php';

                break;
            case $enLanguageId:
                $defaultExcludedTerm = require $this->shopwareCoreDir . '/Migration/Fixtures/stopwords/en.php';

                break;
            default:
                /** @var PreResetExcludedSearchTermEvent $preResetExcludedSearchTermEvent */
                $preResetExcludedSearchTermEvent = $this->eventDispatcher->dispatch(new PreResetExcludedSearchTermEvent($searchConfigId, [], $context));
                $defaultExcludedTerm = $preResetExcludedSearchTermEvent->getExcludedTerms();
        }

        $this->connection->executeUpdate(
            'UPDATE `product_search_config` SET `excluded_terms` = :excludedTerms WHERE `id` = :id',
            [
                'excludedTerms' => json_encode($defaultExcludedTerm),
                'id' => $searchConfigId,
            ]
        );

        return new JsonResponse([
            'success' => true,
        ]);
    }

    private function fetchLanguageIdByName(string $isoCode, Connection $connection): ?string
    {
        $languageId = $connection->fetchColumn(
            '
            SELECT `language`.id FROM `language`
            INNER JOIN locale ON language.translation_code_id = locale.id
            WHERE `code` = :code',
            ['code' => $isoCode]
        );

        return $languageId === false ? null : Uuid::fromBytesToHex($languageId);
    }

    private function getLatestApiVersion(): int
    {
        $sortedSupportedApiVersions = array_values($this->supportedApiVersions);
        usort($sortedSupportedApiVersions, 'version_compare');

        return array_pop($sortedSupportedApiVersions);
    }
}
