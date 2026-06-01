<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Loader;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileSourceLoader
{
    /**
     * @var list<string>
     */
    private const SHOPWARE_TWIG_NAMESPACES = [
        'Administration',
        'Elasticsearch',
        'Framework',
        'Storefront',
    ];

    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $pluginRepository,
        private readonly EntityRepository $appRepository,
    ) {
    }

    /**
     * @param list<string> $twigNamespaces
     *
     * @return array<string, array{sourceName: string, sourceType: string, sourceIcon: string|null}>
     */
    public function load(array $twigNamespaces, Context $context): array
    {
        $twigNamespaces = array_values(array_unique($twigNamespaces));
        $sources = [];

        foreach ($twigNamespaces as $twigNamespace) {
            if (\in_array($twigNamespace, self::SHOPWARE_TWIG_NAMESPACES, true)) {
                $sources[$twigNamespace] = [
                    'sourceName' => 'Shopware',
                    'sourceType' => 'shopware',
                    'sourceIcon' => null,
                ];
            }
        }

        $extensionNamespaces = array_values(array_diff($twigNamespaces, array_keys($sources)));
        if ($extensionNamespaces === []) {
            return $sources;
        }

        foreach ($this->loadPluginSources($extensionNamespaces, $context) as $twigNamespace => $source) {
            $sources[$twigNamespace] = $source;
        }

        $extensionNamespaces = array_values(array_diff($extensionNamespaces, array_keys($sources)));
        foreach ($this->loadAppSources($extensionNamespaces, $context) as $twigNamespace => $source) {
            $sources[$twigNamespace] = $source;
        }

        foreach ($twigNamespaces as $twigNamespace) {
            $sources[$twigNamespace] ??= [
                'sourceName' => $twigNamespace,
                'sourceType' => 'bundle',
                'sourceIcon' => null,
            ];
        }

        return $sources;
    }

    /**
     * @param list<string> $twigNamespaces
     *
     * @return array<string, array{sourceName: string, sourceType: string, sourceIcon: string|null}>
     */
    private function loadPluginSources(array $twigNamespaces, Context $context): array
    {
        if ($twigNamespaces === []) {
            return [];
        }

        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('name', $twigNamespaces));
        $plugins = $this->pluginRepository->search($criteria, $context);

        $sources = [];
        foreach ($plugins as $plugin) {
            if (!$plugin instanceof PluginEntity) {
                continue;
            }

            $sources[$plugin->getName()] = [
                'sourceName' => $this->getDisplayName($plugin->getName(), $plugin->getTranslation('label')),
                'sourceType' => 'plugin',
                'sourceIcon' => $plugin->getIcon(),
            ];
        }

        return $sources;
    }

    /**
     * @param list<string> $twigNamespaces
     *
     * @return array<string, array{sourceName: string, sourceType: string, sourceIcon: string|null}>
     */
    private function loadAppSources(array $twigNamespaces, Context $context): array
    {
        if ($twigNamespaces === []) {
            return [];
        }

        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('name', $twigNamespaces));
        $apps = $this->appRepository->search($criteria, $context);

        $sources = [];
        foreach ($apps as $app) {
            if (!$app instanceof AppEntity) {
                continue;
            }

            $sources[$app->getName()] = [
                'sourceName' => $this->getDisplayName($app->getName(), $app->getTranslation('label')),
                'sourceType' => 'app',
                'sourceIcon' => $app->getIcon(),
            ];
        }

        return $sources;
    }

    private function getDisplayName(string $technicalName, mixed $translatedLabel): string
    {
        if (\is_string($translatedLabel) && $translatedLabel !== '') {
            return $translatedLabel;
        }

        return $technicalName;
    }
}
