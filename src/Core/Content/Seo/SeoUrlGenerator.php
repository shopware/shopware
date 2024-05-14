<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

#[Package('buyers-experience')]
class SeoUrlGenerator
{
    final public const ESCAPE_SLUGIFY = 'slugifyurlencode';

    private readonly TwigVariableParser $twigVariableParser;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
        private readonly Environment $twig,
        TwigVariableParserFactory $parserFactory,
        private readonly LoggerInterface $logger,
    ) {
        $this->twigVariableParser = $parserFactory->getParser($twig);
    }

    /**
     * @param array<string|array<string, string>> $ids
     *
     * @return iterable<SeoUrlEntity>
     */
    public function generate(array $ids, string $template, SeoUrlRouteInterface $route, Context $context, SalesChannelEntity $salesChannel): iterable
    {
        $criteria = new Criteria($ids);
        $route->prepareCriteria($criteria, $salesChannel);

        $config = $route->getConfig();

        $repository = $this->definitionRegistry->getRepository($config->getDefinition()->getEntityName());

        if ($this->loadTwigTemplate($config, $template)) {
            $associations = $this->getAssociations($template, $repository->getDefinition());
            $criteria->addAssociations($associations);

            $criteria->setLimit(50);

            /** @var RepositoryIterator<LandingPageCollection|CategoryCollection|ProductCollection> $iterator */
            $iterator = $context->enableInheritance(static fn (Context $context): RepositoryIterator => new RepositoryIterator($repository, $context, $criteria));

            while ($entities = $iterator->fetch()) {
                yield from $this->generateUrls($route, $config, $salesChannel, $entities, $this->getTemplateName($template));
            }
        }
    }

    /**
     * @param EntityCollection<Entity> $entities
     *
     * @return iterable<SeoUrlEntity>
     */
    private function generateUrls(
        SeoUrlRouteInterface $seoUrlRoute,
        SeoUrlRouteConfig $config,
        SalesChannelEntity $salesChannel,
        EntityCollection $entities,
        string $templateName
    ): iterable {
        $request = $this->requestStack->getMainRequest();

        $basePath = $request ? $request->getBasePath() : '';

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

            $seoPathInfo = $this->getSeoPathInfo($mapping, $config, $templateName);

            if ($seoPathInfo === null || $seoPathInfo === '') {
                continue;
            }

            $copy->setSeoPathInfo($seoPathInfo);
            $copy->setSalesChannelId($salesChannel->getId());

            yield $copy;
        }
    }

    private function getSeoPathInfo(SeoUrlMapping $mapping, SeoUrlRouteConfig $config, string $templateName): ?string
    {
        try {
            return trim($this->twig->render($templateName, $mapping->getSeoPathInfoContext()));
        } catch (Error $error) {
            $this->logger->warning('Error received on rendering SEO URL template', [
                'exception' => $error,
                'mapping_entity_type' => \get_class($mapping->getEntity()),
                'mapping_error' => $mapping->getError(),
                'mapping_info_path' => $mapping->getInfoPathContext(),
                'mapping' => $mapping,
            ]);

            if (!$config->getSkipInvalid()) {
                throw SeoException::invalidTemplate('Error: ' . $error->getMessage());
            }

            return null;
        }
    }

    private function loadTwigTemplate(SeoUrlRouteConfig $config, string $template): bool
    {
        $templateName = $this->getTemplateName($template);
        $template = '{% autoescape \'' . self::ESCAPE_SLUGIFY . "' %}$template{% endautoescape %}";
        $this->twig->setLoader(new ChainLoader([
            new ArrayLoader([$templateName => $template]),
            $this->twig->getLoader(),
        ]));

        try {
            $this->twig->loadTemplate($this->twig->getTemplateClass($templateName), $templateName);
        } catch (SyntaxError $syntaxError) {
            $this->logger->warning('Error initializing SEO URL template', [
                'exception' => $syntaxError,
                'template' => $template,
                'template_name' => $templateName,
            ]);

            if (!$config->getSkipInvalid()) {
                throw SeoException::invalidTemplate('Syntax error: ' . $syntaxError->getMessage());
            }

            return false;
        }

        return true;
    }

    private function getTemplateName(string $template): string
    {
        return 'seo_url_template_' . \md5($template);
    }

    private function removePrefix(string $subject, string $prefix): string
    {
        if (!$prefix || mb_strpos($subject, $prefix) !== 0) {
            return $subject;
        }

        return mb_substr($subject, mb_strlen($prefix));
    }

    /**
     * @return array<string>
     */
    private function getAssociations(string $template, EntityDefinition $definition): array
    {
        try {
            $variables = $this->twigVariableParser->parse($template);
        } catch (\Exception $e) {
            throw SeoException::invalidTemplate($e->getMessage());
        }

        $associations = [];
        foreach ($variables as $variable) {
            $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $variable, true);

            $lastField = end($fields);

            $runtime = new Runtime();

            if ($lastField && $lastField->getFlag(Runtime::class)) {
                $associations = array_merge($associations, $runtime->getDepends());
            }

            $associations[] = EntityDefinitionQueryHelper::getAssociationPath($variable, $definition);
        }

        return array_filter(array_unique($associations));
    }
}
