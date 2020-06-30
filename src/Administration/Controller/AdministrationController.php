<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
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
     * @var string|null
     */
    private $cspHeaderTemplate;

    public function __construct(
        TemplateFinder $finder,
        FirstRunWizardClient $firstRunWizardClient,
        SnippetFinderInterface $snippetFinder,
        $supportedApiVersions,
        ?string $cspHeaderTemplate = null
    ) {
        $this->finder = $finder;
        $this->firstRunWizardClient = $firstRunWizardClient;
        $this->snippetFinder = $snippetFinder;
        $this->supportedApiVersions = $supportedApiVersions;
        $this->cspHeaderTemplate = $cspHeaderTemplate;
    }

    /**
     * @RouteScope(scopes={"administration"})
     * @Route("/admin", defaults={"auth_required"=false}, name="administration.index", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $template = $this->finder->find('@Administration/administration/index.html.twig');
        $nonce = base64_encode(random_bytes(8));

        $response = $this->render($template, [
            'features' => FeatureConfig::getAll(),
            'systemLanguageId' => Defaults::LANGUAGE_SYSTEM,
            'defaultLanguageIds' => [Defaults::LANGUAGE_SYSTEM],
            'systemCurrencyId' => Defaults::CURRENCY,
            'liveVersionId' => Defaults::LIVE_VERSION,
            'firstRunWizard' => $this->firstRunWizardClient->frwShouldRun(),
            'apiVersion' => $this->getLatestApiVersion(),
            'cspNonce' => $nonce,
        ]);

        if ($this->cspHeaderTemplate !== null) {
            $csp = str_replace('%nonce%', $nonce, $this->cspHeaderTemplate);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
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

    private function getLatestApiVersion(): int
    {
        $sortedSupportedApiVersions = array_values($this->supportedApiVersions);
        usort($sortedSupportedApiVersions, 'version_compare');

        return array_pop($sortedSupportedApiVersions);
    }
}
