<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\SlugifyInterface;
use Shopware\Core\Content\Seo\Exception\InvalidTemplateException;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Error\SyntaxError;
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

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        SlugifyInterface $slugify,
        RouterInterface $router,
        RequestStack $requestStack
    ) {
        $this->definitionRegistry = $definitionRegistry;

        $this->router = $router;

        $this->initTwig($slugify);
        $this->requestStack = $requestStack;
    }

    public function generate(array $ids, string $template, SeoUrlRouteInterface $route, Context $context, ?SalesChannelEntity $salesChannel): iterable
    {
        $criteria = new Criteria($ids);
        $route->prepareCriteria($criteria);

        $config = $route->getConfig();

        $repository = $this->definitionRegistry->getRepository($config->getDefinition()->getEntityName());

        $entities = $context->enableInheritance(static function (Context $context) use ($repository, $criteria) {
            return $context->disableCache(static function (Context $context) use ($repository, $criteria) {
                return $repository->search($criteria, $context)->getEntities();
            });
        });

        $this->setTwigTemplate($config, $template);

        yield from $this->generateUrls($route, $config, $salesChannel, $entities);
    }

    private function initTwig(SlugifyInterface $slugify): void
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

    private function generateUrls(SeoUrlRouteInterface $seoUrlRoute, SeoUrlRouteConfig $config, ?SalesChannelEntity $salesChannel, EntityCollection $entities): iterable
    {
        $request = $this->requestStack->getMasterRequest();

        $basePath = $request ? $request->getBasePath() : '';

        /** @var Entity $entity */
        foreach ($entities as $entity) {
            $seoUrl = new SeoUrlEntity();
            $seoUrl->setForeignKey($entity->getUniqueIdentifier());

            $seoUrl->setIsCanonical(true);
            $seoUrl->setIsModified(false);
            $seoUrl->setIsDeleted(false);

            $copy = clone $seoUrl;

            $mapping = $seoUrlRoute->getMapping($entity, $salesChannel);

            $copy->setError($mapping->getError());
            $pathInfo = $this->router->generate($config->getRouteName(), $mapping->getInfoPathContext());
            $pathInfo = $this->removePrefix($pathInfo, $basePath);

            $copy->setPathInfo($pathInfo);

            $seoPathInfo = $this->getSeoPathInfo($mapping, $config);

            if ($seoPathInfo === null || $seoPathInfo === '') {
                continue;
            }

            $copy->setSeoPathInfo($seoPathInfo);

            if ($salesChannel !== null) {
                $copy->setSalesChannelId($salesChannel->getId());
            } else {
                $copy->setSalesChannelId(null);
            }

            yield $copy;
        }
    }

    private function getSeoPathInfo(SeoUrlMapping $mapping, SeoUrlRouteConfig $config): ?string
    {
        try {
            return trim($this->twig->render('template', $mapping->getSeoPathInfoContext()));
        } catch (Error $error) {
            if (!$config->getSkipInvalid()) {
                throw $error;
            }

            return null;
        }
    }

    private function setTwigTemplate(SeoUrlRouteConfig $config, string $template): void
    {
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

    private function removePrefix(string $subject, string $prefix): string
    {
        if (!$prefix || mb_strpos($subject, $prefix) !== 0) {
            return $subject;
        }

        return mb_substr($subject, mb_strlen($prefix));
    }
}
