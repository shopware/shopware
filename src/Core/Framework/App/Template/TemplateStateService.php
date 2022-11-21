<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Template;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class TemplateStateService
{
    /**
     * @var EntityRepository
     */
    private $templateRepo;

    public function __construct(EntityRepository $templateRepo)
    {
        $this->templateRepo = $templateRepo;
    }

    public function activateAppTemplates(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', false));

        /** @var array<string> $templates */
        $templates = $this->templateRepo->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(function (string $id) {
            return ['id' => $id, 'active' => true];
        }, $templates);

        $this->templateRepo->update($updateSet, $context);
    }

    public function deactivateAppTemplates(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        /** @var array<string> $templates */
        $templates = $this->templateRepo->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(function (string $id) {
            return ['id' => $id, 'active' => false];
        }, $templates);

        $this->templateRepo->update($updateSet, $context);
    }
}
