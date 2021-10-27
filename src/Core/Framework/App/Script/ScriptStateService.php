<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Script;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @internal only for use by the app-system
 */
class ScriptStateService
{
    private EntityRepositoryInterface $scriptRepo;

    public function __construct(EntityRepositoryInterface $scriptRepo)
    {
        $this->scriptRepo = $scriptRepo;
    }

    public function activateAppScripts(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', false));

        $templates = $this->scriptRepo->searchIds($criteria, $context);

        $updateSet = array_map(function (string $id) {
            return ['id' => $id, 'active' => true];
        }, $templates->getIds());

        $this->scriptRepo->update($updateSet, $context);
    }

    public function deactivateAppScripts(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        $templates = $this->scriptRepo->searchIds($criteria, $context);

        $updateSet = array_map(function (string $id) {
            return ['id' => $id, 'active' => false];
        }, $templates->getIds());

        $this->scriptRepo->update($updateSet, $context);
    }
}
