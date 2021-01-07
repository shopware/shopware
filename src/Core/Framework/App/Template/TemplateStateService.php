<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Template;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class TemplateStateService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $templateRepo;

    public function __construct(EntityRepositoryInterface $templateRepo)
    {
        $this->templateRepo = $templateRepo;
    }

    public function activateAppTemplates(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', false));

        $templates = $this->templateRepo->searchIds($criteria, $context);

        $updateSet = array_map(function (string $id) {
            return ['id' => $id, 'active' => true];
        }, $templates->getIds());

        $this->templateRepo->update($updateSet, $context);
    }

    public function deactivateAppTemplates(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        $templates = $this->templateRepo->searchIds($criteria, $context);

        $updateSet = array_map(function (string $id) {
            return ['id' => $id, 'active' => false];
        }, $templates->getIds());

        $this->templateRepo->update($updateSet, $context);
    }
}
