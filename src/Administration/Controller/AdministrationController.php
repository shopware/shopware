<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\Framework\Twig\TemplateFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    public function __construct(TemplateFinder $finder, FirstRunWizardClient $firstRunWizardClient)
    {
        $this->finder = $finder;
        $this->firstRunWizardClient = $firstRunWizardClient;
    }

    /**
     * @RouteScope(scopes={"administration"})
     * @Route("/admin", name="administration.index", methods={"GET"})
     */
    public function index(): Response
    {
        $template = $this->finder->find('@Administration/administration/index.html.twig');

        return $this->render($template, [
            'features' => FeatureConfig::getAll(),
            'systemLanguageId' => Defaults::LANGUAGE_SYSTEM,
            'defaultLanguageIds' => [Defaults::LANGUAGE_SYSTEM],
            'systemCurrencyId' => Defaults::CURRENCY,
            'liveVersionId' => Defaults::LIVE_VERSION,
            'firstRunWizard' => $this->firstRunWizardClient->frwShouldRun(),
        ]);
    }
}
