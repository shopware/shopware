<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Twig\Components\UxComponent;
use Shopware\Storefront\Framework\Twig\Components\UxComponentCollection;
use Symfony\UX\TwigComponent\ComponentFactory;
use Symfony\UX\TwigComponent\ComponentMetadata;
use Symfony\UX\TwigComponent\Twig\PropsNode;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Path;
use Doctrine\DBAL\Connection; 
use Twig\Environment;

#[Package('framework')]
class UxComponentHelper
{
    private string $componentsDir;
    private const MAIN_NAMESPACE = 'Storefront';

    public function __construct(
        private string $componentDirectory,
        private string $projectDir,
        private array $bundlesMetadata,
        private Environment $twig,
        private readonly NamespaceHierarchyBuilder $namespaceHierarchyBuilder,
        private readonly ComponentFactory $componentFactory,
        private readonly Connection $connection,
    ) {
        $this->componentDirectory = $componentDirectory ?? 'Resources/views/components';
    }

    public function getComponents(): UxComponentCollection
    {
        $components = new UxComponentCollection();
        foreach ($this->findAnonymousComponents() as $component) {
            $components->add($component);
        }

        return $components;
    }

    public function findAnonymousComponents(): array
    {
        $dirs = array_merge($this->getBundleDirs(), $this->getAppDirs());

        $components = [];
        $finderTemplates = new Finder();
        $finderTemplates->files()
            ->in(array_keys($dirs))
            ->notPath('/_')
            ->name('*.html.twig')
        ;

        foreach ($finderTemplates as $template) {
            $componentNamespace = $dirs[Path::getDirectory($template->getRealPath())] ?? self::MAIN_NAMESPACE;
            $component = $this->getComponentFromTemplate($template, $componentNamespace);

            $components[$component->getName()] = $component;
        }

        return $components;
    }

    private function getBundleDirs(): array
    {
        $namespaceHierarchy = $this->namespaceHierarchyBuilder->buildHierarchy();   
        $namespaces = array_keys($namespaceHierarchy);

        $dirs = [];

        foreach ($namespaces as $namespace) {

            if (!isset($this->bundlesMetadata[$namespace])) {
                continue;
            }

            $path = $this->bundlesMetadata[$namespace]['path'];
            $componentsDir = Path::join($path, $this->componentDirectory);

            if (!is_dir($componentsDir)) {
                continue;
            }

            $dirs[$componentsDir] = $namespace;
        }

        return $dirs;
    }

    private function getAppDirs(): array
    {
        $dirs = [];

        $templates = $this->connection->fetchAllAssociative('
            SELECT
                `app_template`.`path` AS `path`,
                `app`.`name` AS `namespace`,
                `app`.`path` AS `appPath`	
            FROM `app_template`
            INNER JOIN `app` ON `app_template`.`app_id` = `app`.`id`
            WHERE `app_template`.`active` = 1 AND `app`.`active` = 1 
            AND `app_template`.`path` LIKE "%components/%"
        ');

        foreach ($templates as $template) 
        {
            $appPath = $this->getAbsoluteAppPath($template['appPath']);
            $componentDir = $this->getComponentAppDir($appPath, $template['path']);

            $dirs[$componentDir] = $template['namespace'];
        }

        return $dirs;
    }

    public function getAnonymousComponentProperties(ComponentMetadata $metadata): array
    {
        $source = $this->twig->load($metadata->getTemplate())->getSourceContext();
        $tokenStream = $this->twig->tokenize($source);
        $moduleNode = $this->twig->parse($tokenStream);

        $propsNode = null;
        foreach ($moduleNode->getNode('body') as $bodyNode) {
            foreach ($bodyNode as $node) {
                if (PropsNode::class === $node::class) {
                    $propsNode = $node;
                    break 2;
                }
            }
        }
        if (!$propsNode instanceof PropsNode) {
            return [];
        }

        $propertyNames = $propsNode->getAttribute('names');
        $properties = array_combine($propertyNames, $propertyNames);
        foreach ($propertyNames as $propName) {
            if ($propsNode->hasNode($propName)
                && ($valueNode = $propsNode->getNode($propName))
                && $valueNode->hasAttribute('value')
            ) {
                $value = $valueNode->getAttribute('value');
                if (\is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                } else {
                    $value = json_encode($value);
                }
                $properties[$propName] = $propName.' = '.$value;
            }
        }

        return $properties;
    }

    public function getComponentFromTemplate($template, string $componentNamespace) 
    {
        $componentName = $this->getComponentNameFromPath($template->getRelativePathname());

        $component = new UxComponent(
            $componentName,
            $template->getRealPath(),
            $componentNamespace
        );

        return $component;
    }

    private function getNamespacePath($namespace)
    {
        if (!isset($this->bundlesMetadata[$namespace])) {
            return null;
        }

        return $this->bundlesMetadata[$namespace]['path'];
    }

    private function getAbsoluteAppPath($appPath) 
    {
        $absolutePath = Path::join($this->projectDir, $appPath);

        if (!is_dir($absolutePath)) {
            return null;
        }

        return $absolutePath;
    }

    private function getComponentAppDir($appPath, $templatePath) 
    {
        $path = $this->getComponentAppPath($appPath, $templatePath);

        return Path::getDirectory($path);
    }

    private function getComponentAppPath($appPath, $templatePath) 
    {
        if (str_starts_with($templatePath, 'components/')) {
            $templatePath = str_replace('components/', '', $templatePath);
        }

        return Path::join($appPath, $this->componentDirectory, $templatePath);
    }

    private function getComponentNameFromPath($templateRelativePath) 
    {
        if (str_starts_with($templateRelativePath, 'components/')) {
            $templateRelativePath = str_replace('components/', '', $templateRelativePath);
        }

        $componentName = str_replace(\DIRECTORY_SEPARATOR, ':', $templateRelativePath);
        $componentName = substr($componentName, 0, -10); // remove file extension ".html.twig"

        return $componentName;
    }
}
