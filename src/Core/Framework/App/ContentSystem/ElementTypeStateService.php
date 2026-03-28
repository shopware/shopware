<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ContentSystem;

use Shopware\Core\Framework\App\Aggregate\AppContentSystemElementType\AppContentSystemElementTypeCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * Toggles the `active` column on app_content_system_element_type when an app
 * is activated or deactivated. DatabaseTypeLoader queries WHERE active = 1,
 * so deactivated types are excluded from the registry on the next request.
 */
#[Package('framework')]
class ElementTypeStateService
{
    /**
     * @param EntityRepository<AppContentSystemElementTypeCollection> $elementTypeRepository
     */
    public function __construct(
        private readonly EntityRepository $elementTypeRepository,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
    ) {
    }

    public function activateElementTypes(string $appId, Context $context): void
    {
        $this->updateElementTypes($appId, $context, false, true);
    }

    public function deactivateElementTypes(string $appId, Context $context): void
    {
        $this->updateElementTypes($appId, $context, true, false);
    }

    private function updateElementTypes(string $appId, Context $context, bool $currentActiveState, bool $newActiveState): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        // Only touch types that actually need toggling, skip those already in the desired state
        $criteria->addFilter(new EqualsFilter('active', $currentActiveState));

        $ids = $this->elementTypeRepository->searchIds($criteria, $context)->getIds();

        if ($ids === []) {
            return;
        }

        $updateSet = array_map(static fn (string $id) => ['id' => $id, 'active' => $newActiveState], $ids);

        $this->elementTypeRepository->update($updateSet, $context);
        $this->registry->invalidate();
    }
}
