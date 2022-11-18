<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @package core
 */
class PluginIdProvider
{
    /**
     * @var EntityRepository
     */
    private $pluginRepo;

    /**
     * @internal
     */
    public function __construct(EntityRepository $pluginRepo)
    {
        $this->pluginRepo = $pluginRepo;
    }

    public function getPluginIdByBaseClass(string $pluginBaseClassName, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('baseClass', $pluginBaseClassName));
        $pluginIds = $this->pluginRepo->searchIds($criteria, $context)->getIds();

        return array_pop($pluginIds);
    }
}
