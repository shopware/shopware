<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Doctrine\DBAL\Connection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigLoaderConfigCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * @internal
 */
#[Package('core')]
class EntityTemplateLoader implements LoaderInterface, EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string, array<string, array{template: string, updatedAt: \DateTimeInterface|null}|null>>
     */
    private array $databaseTemplateCache = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $environment
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return ['app_template.written' => 'reset'];
    }

    public function reset(): void
    {
        $this->databaseTemplateCache = [];
    }

    public function getSourceContext(string $name): Source
    {
        $template = $this->findDatabaseTemplate($name);

        if (!$template) {
            throw new LoaderError(sprintf('Template "%s" is not defined.', $name));
        }

        return new Source($template['template'], $name);
    }

    public function getCacheKey(string $name): string
    {
        return $name;
    }

    public function isFresh(string $name, int $time): bool
    {
        $template = $this->findDatabaseTemplate($name);
        if (!$template) {
            return false;
        }

        return $template['updatedAt'] === null || $template['updatedAt']->getTimestamp() < $time;
    }

    /**
     * @return bool
     */
    public function exists(string $name)
    {
        $template = $this->findDatabaseTemplate($name);
        if (!$template) {
            return false;
        }

        return true;
    }

    /**
     * @return array{template: string, updatedAt: \DateTimeInterface|null}|null
     */
    private function findDatabaseTemplate(string $name): ?array
    {
        if (EnvironmentHelper::getVariable('DISABLE_EXTENSIONS', false)) {
            return null;
        }

        /*
         * In dev env app templates are directly loaded over the filesystem
         * @see TwigLoaderConfigCompilerPass::addAppTemplatePaths()
         */
        if ($this->environment === 'dev') {
            return null;
        }

        $templateName = $this->splitTemplateName($name);
        $namespace = $templateName['namespace'];
        $path = $templateName['path'];

        if (empty($this->databaseTemplateCache)) {
            /** @var array<array{path: string, template: string, updatedAt: string|null, namespace: string}> $templates */
            $templates = $this->connection->fetchAllAssociative('
                SELECT
                    `app_template`.`path` AS `path`,
                    `app_template`.`template` AS `template`,
                    `app_template`.`updated_at` AS `updatedAt`,
                    `app`.`name` AS `namespace`
                FROM `app_template`
                INNER JOIN `app` ON `app_template`.`app_id` = `app`.`id`
                WHERE `app_template`.`active` = 1 AND `app`.`active` = 1
            ');

            foreach ($templates as $template) {
                $this->databaseTemplateCache[$template['path']][$template['namespace']] = [
                    'template' => $template['template'],
                    'updatedAt' => $template['updatedAt'] ? new \DateTimeImmutable($template['updatedAt']) : null,
                ];
            }
        }

        if (\array_key_exists($path, $this->databaseTemplateCache) && \array_key_exists($namespace, $this->databaseTemplateCache[$path])) {
            return $this->databaseTemplateCache[$path][$namespace];
        }

        // we have already loaded all DB templates
        // if the namespace is not included return null
        return $this->databaseTemplateCache[$path][$namespace] = null;
    }

    /**
     * @return array{namespace: string, path: string}
     */
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
