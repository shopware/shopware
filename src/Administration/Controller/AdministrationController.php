<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
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

    public function __construct(TemplateFinder $finder)
    {
        $this->finder = $finder;
    }

    /**
     * @Route("/admin", name="administration.index", methods={"GET"})
     */
    public function index(): Response
    {
        $template = $this->finder->find('administration/index.html.twig');

        return $this->render($template, [
            'features' => FeatureConfig::getAll(),
            'systemLanguageId' => Defaults::LANGUAGE_SYSTEM,
            'defaultLanguageIds' => [Defaults::LANGUAGE_SYSTEM, Defaults::LANGUAGE_SYSTEM_DE],
            'liveVersionId' => Defaults::LIVE_VERSION,
        ]);
    }
}
