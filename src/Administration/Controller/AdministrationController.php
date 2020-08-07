<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\KnownIps\KnownIpsCollectorInterface;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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

    private $supportedApiVersions;

    /**
     * @var KnownIpsCollectorInterface
     */
    private $knownIpsCollector;

    public function __construct(
        TemplateFinder $finder,
        FirstRunWizardClient $firstRunWizardClient,
        SnippetFinderInterface $snippetFinder,
        $supportedApiVersions,
        KnownIpsCollectorInterface $knownIpsCollector
    ) {
        $this->finder = $finder;
        $this->firstRunWizardClient = $firstRunWizardClient;
        $this->snippetFinder = $snippetFinder;
        $this->supportedApiVersions = $supportedApiVersions;
        $this->knownIpsCollector = $knownIpsCollector;
    }

    /**
     * @RouteScope(scopes={"administration"})
     * @Route("/admin", defaults={"auth_required"=false}, name="administration.index", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $template = $this->finder->find('@Administration/administration/index.html.twig');

        return $this->render($template, [
            'features' => FeatureConfig::getAll(),
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
     * @RouteScope(scopes={"administration"})
     * @Route("/api/v{version}/_admin/snippets", name="api.admin.snippets", methods={"GET"})
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
     * @RouteScope(scopes={"administration"})
     * @Route("/api/v{version}/_admin/known-ips", name="api.admin.known-ips", methods={"GET"})
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

    private function getLatestApiVersion(): int
    {
        $sortedSupportedApiVersions = array_values($this->supportedApiVersions);
        usort($sortedSupportedApiVersions, 'version_compare');

        return array_pop($sortedSupportedApiVersions);
    }
}
