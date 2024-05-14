<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Template;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class TemplateStateService
{
    public function __construct(private readonly EntityRepository $templateRepo)
    {
    }

    public function activateAppTemplates(string $appId, Context $context): void
    {
        $this->updateAppTemplates($appId, $context, false, true);
    }

    public function deactivateAppTemplates(string $appId, Context $context): void
    {
        $this->updateAppTemplates($appId, $context, true, false);
    }

    private function updateAppTemplates(string $appId, Context $context, bool $currentActiveState, bool $newActiveState): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', $currentActiveState));

        /** @var array<string> $templates */
        $templates = $this->templateRepo->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(fn (string $id) => ['id' => $id, 'active' => $newActiveState], $templates);

        $this->templateRepo->update($updateSet, $context);
    }
}
