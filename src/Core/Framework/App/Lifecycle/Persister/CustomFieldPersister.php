<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFields;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class CustomFieldPersister
{
    public function __construct(private readonly EntityRepository $customFieldSetRepository)
    {
    }

    /**
     * @internal only for use by the app-system
     */
    public function updateCustomFields(Manifest $manifest, string $appId, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($manifest, $appId): void {
            $this->deleteCustomFieldsForApp($appId, $context);
            $this->addCustomFields($manifest->getCustomFields(), $appId, $context);
        });
    }

    private function deleteCustomFieldsForApp(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        /** @var array<string> $ids */
        $ids = $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();

        if (!empty($ids)) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], $ids);

            $this->customFieldSetRepository->delete($ids, $context);
        }
    }

    private function addCustomFields(?CustomFields $customFields, string $appId, Context $context): void
    {
        if (!$customFields || empty($customFields->getCustomFieldSets())) {
            return;
        }

        $payload = $this->generateCustomFieldSets($customFields->getCustomFieldSets(), $appId);

        $this->customFieldSetRepository->upsert($payload, $context);
    }

    private function generateCustomFieldSets(array $customFieldSets, string $appId): array
    {
        $payload = [];

        /** @var CustomFieldSet $customFieldSet */
        foreach ($customFieldSets as $customFieldSet) {
            $payload[] = $customFieldSet->toEntityArray($appId);
        }

        return $payload;
    }
}
