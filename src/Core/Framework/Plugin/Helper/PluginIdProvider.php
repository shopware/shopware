<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Helper;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PluginIdProvider
{
    /**
     * @var RepositoryInterface
     */
    private $pluginRepo;

    /**
     * @param RepositoryInterface $pluginRepo
     */
    public function __construct(RepositoryInterface $pluginRepo)
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
