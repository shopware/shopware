<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Plugin\PluginEntity;

class SystemConfigLoader extends AbstractSystemConfigLoader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepository;

    public function __construct(EntityRepositoryInterface $repository, EntityRepositoryInterface $pluginRepository)
    {
        $this->repository = $repository;
        $this->pluginRepository = $pluginRepository;
    }

    public function getDecorated(): AbstractSystemConfigLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(?string $salesChannelId): array
    {
        $criteria = new Criteria();
        $criteria->setTitle('system-config::load');

        if ($salesChannelId === null) {
            $criteria->addFilter(new EqualsFilter('salesChannelId', null));
        } else {
            $criteria->addFilter(
                new MultiFilter(
                    MultiFilter::CONNECTION_OR,
                    [
                        new EqualsFilter('salesChannelId', $salesChannelId),
                        new EqualsFilter('salesChannelId', null),
                    ]
                )
            );
        }

        $criteria->addSorting(
            new FieldSorting('salesChannelId', FieldSorting::ASCENDING),
            new FieldSorting('id', FieldSorting::ASCENDING)
        );
        $criteria->setLimit(500);

        $systemConfigs = new SystemConfigCollection();
        $iterator = new RepositoryIterator($this->repository, Context::createDefaultContext(), $criteria);

        while ($chunk = $iterator->fetch()) {
            $systemConfigs->merge($chunk->getEntities());
        }

        return $this->buildSystemConfigArray($systemConfigs);
    }

    private function buildSystemConfigArray(SystemConfigCollection $systemConfigs): array
    {
        $configValues = [];

        foreach ($systemConfigs as $systemConfig) {
            $keys = explode('.', $systemConfig->getConfigurationKey());

            $configValues = $this->getSubArray($configValues, $keys, $systemConfig->getConfigurationValue());
        }

        return $this->filterNotActivatedPlugins($configValues);
    }

    /**
     * @param array|bool|float|int|string|null $value
     */
    private function getSubArray(array $configValues, array $keys, $value): array
    {
        $key = array_shift($keys);

        if (empty($keys)) {
            $configValues[$key] = $value;
        } else {
            if (!\array_key_exists($key, $configValues)) {
                $configValues[$key] = [];
            }

            $configValues[$key] = $this->getSubArray($configValues[$key], $keys, $value);
        }

        return $configValues;
    }

    private function filterNotActivatedPlugins(array $configValues): array
    {
        $notActivatedPlugins = $this->getNotActivatedPlugins();

        foreach (array_keys($configValues) as $key) {
            $notActivatedPlugin = $notActivatedPlugins->filter(function (PluginEntity $plugin) use ($key) {
                return $plugin->getName() === $key;
            })->first();

            if ($notActivatedPlugin) {
                unset($configValues[$key]);
            }
        }

        return $configValues;
    }

    private function getNotActivatedPlugins(): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', false));

        return $this->pluginRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }
}
