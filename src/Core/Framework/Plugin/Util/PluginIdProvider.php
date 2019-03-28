<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PluginIdProvider
{
    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    public function __construct(EntityRepositoryInterface $pluginRepo)
    {
        $this->pluginRepo = $pluginRepo;
    }

    public function getPluginIdByTechnicalName(string $pluginName, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $pluginName));
        $pluginIds = $this->pluginRepo->searchIds($criteria, $context)->getIds();

        return array_pop($pluginIds);
    }
}
