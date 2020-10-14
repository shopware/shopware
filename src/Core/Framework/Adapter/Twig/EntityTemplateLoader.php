<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\App\Template\TemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

class EntityTemplateLoader implements LoaderInterface, EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $templateRepository;

    /**
     * @var array
     */
    private $databaseTemplateCache = [];

    public function __construct(EntityRepositoryInterface $templateRepository)
    {
        $this->templateRepository = $templateRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return ['app_template.written' => 'clearInternalCache'];
    }

    public function clearInternalCache(): void
    {
        $this->databaseTemplateCache = [];
    }

    public function getSourceContext($name)
    {
        $template = $this->findDatabaseTemplate($name);

        if (!$template) {
            throw new LoaderError(sprintf('Template "%s" is not defined.', $name));
        }

        return new Source($template->getTemplate(), $name);
    }

    public function getCacheKey($name)
    {
        return $name;
    }

    public function isFresh($name, $time)
    {
        $template = $this->findDatabaseTemplate($name);
        if (!$template) {
            return false;
        }

        return $template->getUpdatedAt() === null || $template->getUpdatedAt()->getTimestamp() < $time;
    }

    public function exists($name)
    {
        $template = $this->findDatabaseTemplate($name);
        if (!$template) {
            return false;
        }

        return true;
    }

    private function findDatabaseTemplate(string $name): ?TemplateEntity
    {
        $templateName = $this->splitTemplateName($name);
        $namespace = $templateName['namespace'];
        $path = $templateName['path'];

        if (array_key_exists($path, $this->databaseTemplateCache)) {
            if (array_key_exists($namespace, $this->databaseTemplateCache[$path])) {
                return $this->databaseTemplateCache[$path][$namespace];
            }

            // we have already loaded all DB templates for this path
            // if the namespace is not included return null
            return $this->databaseTemplateCache[$path][$namespace] = null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('path', $path))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('app.active', true))
            ->addAssociation('app');

        $templates = $this->templateRepository->search($criteria, Context::createDefaultContext());

        /** @var TemplateEntity $template */
        foreach ($templates as $template) {
            $this->databaseTemplateCache[$path][$template->getApp()->getName()] = $template;
        }

        if (array_key_exists($path, $this->databaseTemplateCache)
            && array_key_exists($namespace, $this->databaseTemplateCache[$path])) {
            return $this->databaseTemplateCache[$path][$namespace];
        }

        return $this->databaseTemplateCache[$path][$namespace] = null;
    }

    private function splitTemplateName(string $template): array
    {
        // remove static template inheritance prefix
        if (mb_strpos($template, '@') !== 0) {
            return ['path' => $template, 'namespace' => ''];
        }

        // remove "@"
        $template = mb_substr($template, 1);

        $template = explode('/', $template);
        $namespace = array_shift($template);
        $template = implode('/', $template);

        return ['path' => $template, 'namespace' => $namespace];
    }
}
