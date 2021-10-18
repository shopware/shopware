<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Content\Seo\Exception\InvalidTemplateException;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

class SeoUrlGenerator
{
    public const ESCAPE_SLUGIFY = 'slugifyurlencode';

    private RouterInterface $router;

    private Environment $twig;

    private DefinitionInstanceRegistry $definitionRegistry;

    private RequestStack $requestStack;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        RouterInterface $router,
        RequestStack $requestStack,
        Environment $environment
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->twig = $environment;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_13410) Parameter $salesChannel will be required
     */
    public function generate(array $ids, string $template, SeoUrlRouteInterface $route, Context $context, ?SalesChannelEntity $salesChannel): iterable
    {
        $criteria = new Criteria($ids);
        $route->prepareCriteria($criteria, $salesChannel);

        $config = $route->getConfig();

        $repository = $this->definitionRegistry->getRepository($config->getDefinition()->getEntityName());

        $criteria->setLimit(50);

        /** @var RepositoryIterator $iterator */
        $iterator = $context->enableInheritance(static function (Context $context) use ($repository, $criteria) {
            return new RepositoryIterator($repository, $context, $criteria);
        });

        $this->setTwigTemplate($config, $template);

        while ($entities = $iterator->fetch()) {
            yield from $this->generateUrls($route, $config, $salesChannel, $entities);
        }
    }

    /**
     * @internal (flag:FEATURE_NEXT_13410) Parameter $salesChannel will be required
     */
    private function generateUrls(SeoUrlRouteInterface $seoUrlRoute, SeoUrlRouteConfig $config, ?SalesChannelEntity $salesChannel, EntityCollection $entities): iterable
    {
        $request = $this->requestStack->getMainRequest();

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
        } catch (\Throwable $error) {
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
            $this->twig->loadTemplate($this->twig->getTemplateClass('template'), 'template');
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
