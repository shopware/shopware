<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ContentSystem;

use Shopware\Core\Framework\App\Aggregate\AppContentSystemElementType\AppContentSystemElementTypeCollection;
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
 *
 * The registry implements ResetInterface — runtime-loaded types are cleared
 * between requests, so no explicit registry reset is needed here.
 */
#[Package('framework')]
class ElementTypeStateService
{
    /**
     * @param EntityRepository<AppContentSystemElementTypeCollection> $elementTypeRepository
     */
    public function __construct(
        private readonly EntityRepository $elementTypeRepository,
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
        $criteria->addFilter(new EqualsFilter('active', $currentActiveState));

        $ids = $this->elementTypeRepository->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(static fn (string $id) => ['id' => $id, 'active' => $newActiveState], $ids);

        $this->elementTypeRepository->update($updateSet, $context);
    }
}
