<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo;

use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\Slugify;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Framework\Seo\Exception\InvalidTemplateException;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Storefront\Framework\Seo\SeoUrlTemplate\TemplateGroup;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Error\SyntaxError;
use Twig\Extension\CoreExtension;
use Twig\Extension\EscaperExtension;
use Twig\Loader\ArrayLoader;

class SeoUrlGenerator
{
    public const ESCAPE_SLUGIFY = 'slugifyurlencode';

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        Slugify $slugify,
        RouterInterface $router
    ) {
        $this->definitionRegistry = $definitionRegistry;

        $this->router = $router;

        $this->initTwig($slugify);
    }

    /**
     * @param TemplateGroup[] $templateGroups
     *
     * @throws InvalidTemplateException
     */
    public function generateSeoUrls(Context $context, SeoUrlRouteInterface $seoUrlRoute, array $ids, array $templateGroups, ?SeoUrlRouteConfig $configOverride = null): iterable
    {
        $criteria = new Criteria($ids);
        $seoUrlRoute->prepareCriteria($criteria);

        $config = $configOverride ?? $seoUrlRoute->getConfig();
        $defaultTemplate = $config->getTemplate();

        $repo = $this->definitionRegistry->getRepository($config->getDefinition()->getEntityName());

        $entities = $context->disableCache(static function (Context $context) use ($repo, $criteria) {
            return $repo->search($criteria, $context)->getEntities();
        });

        foreach ($templateGroups as $templateGroup) {
            $template = $templateGroup->getTemplate() ?: $defaultTemplate;
            $config->setTemplate($template);
            $this->setTwigTemplate($config);

            yield from $this->generate($seoUrlRoute, $config, $templateGroup->getSalesChannelIds(), $entities);
        }
    }

    private function initTwig(Slugify $slugify): void
    {
        $this->twig = new Environment(new ArrayLoader());
        $this->twig->setCache(false);
        $this->twig->enableStrictVariables();
        $this->twig->addExtension(new SlugifyExtension($slugify));

        /** @var EscaperExtension $coreExtension */
        $coreExtension = $this->twig->getExtension(EscaperExtension::class);
        $coreExtension->setEscaper(
            self::ESCAPE_SLUGIFY,
            static function ($twig, $string) use ($slugify) {
                return rawurlencode($slugify->slugify($string));
            }
        );
    }

    private function generate(SeoUrlRouteInterface $seoUrlRoute, SeoUrlRouteConfig $config, array $salesChannelIds, EntityCollection $entities): iterable
    {
        /** @var Entity $entity */
        foreach ($entities as $entity) {
            $mapping = $seoUrlRoute->getMapping($entity);

            $seoUrl = new SeoUrlEntity();
            $seoUrl->setForeignKey($entity->getUniqueIdentifier());

            $pathInfo = $this->router->generate($config->getRouteName(), $mapping->getInfoPathContext());
            $seoUrl->setPathInfo($pathInfo);

            $seoPathInfo = $this->getSeoPathInfo($mapping, $config);
            if ($seoPathInfo === null) {
                continue;
            }

            $seoUrl->setSeoPathInfo($seoPathInfo);

            $seoUrl->setIsCanonical(true);
            $seoUrl->setIsModified(false);
            $seoUrl->setIsDeleted(false);
            $seoUrl->setIsValid(true);

            foreach ($salesChannelIds as $salesChannelId) {
                $copy = clone $seoUrl;
                $copy->setSalesChannelId($salesChannelId);

                yield $copy;
            }
        }
    }

    private function getSeoPathInfo(SeoUrlMapping $mapping, SeoUrlRouteConfig $config): ?string
    {
        try {
            return $this->twig->render('template', $mapping->getSeoPathInfoContext());
        } catch (Error $error) {
            if (!$config->getSkipInvalid()) {
                throw $error;
            }

            return null;
        }
    }

    private function setTwigTemplate(SeoUrlRouteConfig $config): void
    {
        $template = $config->getTemplate();
        $template = "{% autoescape '" . self::ESCAPE_SLUGIFY . "' %}$template{% endautoescape %}";
        $this->twig->setLoader(new ArrayLoader(['template' => $template]));

        try {
            $this->twig->loadTemplate('template');
        } catch (SyntaxError $syntaxError) {
            if (!$config->getSkipInvalid()) {
                throw new InvalidTemplateException('Syntax error: ' . $syntaxError->getMessage());
            }
        }
    }
}
