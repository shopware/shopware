<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomFieldService implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var CustomFieldEntity[]|null
     */
    private $customFields;

    public function __construct(EntityRepositoryInterface $attributeRepository)
    {
        $this->attributeRepository = $attributeRepository;
    }

    public function getCustomField(string $attributeName): ?Field
    {
        $attribute = $this->getCustomFields()[$attributeName] ?? null;
        if (!$attribute) {
            return null;
        }

        switch ($attribute->getType()) {
            case CustomFieldTypes::INT:
                return new IntField($attributeName, $attributeName);

            case CustomFieldTypes::FLOAT:
                return new FloatField($attributeName, $attributeName);

            case CustomFieldTypes::BOOL:
                return new BoolField($attributeName, $attributeName);

            case CustomFieldTypes::DATETIME:
                return new DateTimeField($attributeName, $attributeName);

            case CustomFieldTypes::TEXT:
                return new LongTextField($attributeName, $attributeName);

            case CustomFieldTypes::HTML:
                return new LongTextWithHtmlField($attributeName, $attributeName);

            case CustomFieldTypes::JSON:
            default:
                return new JsonField($attributeName, $attributeName);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomFieldEvents::CUSTOM_FIELD_DELETED_EVENT => 'invalidateCache',
            CustomFieldEvents::CUSTOM_FIELD_WRITTEN_EVENT => 'invalidateCache',
        ];
    }

    /**
     * @internal
     */
    public function invalidateCache(): void
    {
        $this->customFields = null;
    }

    /**
     * @return CustomFieldEntity[]
     */
    private function getCustomFields(): array
    {
        if ($this->customFields !== null) {
            return $this->customFields;
        }

        $this->customFields = [];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        // attributes should not be context dependent
        $result = $this->attributeRepository->search($criteria, Context::createDefaultContext());
        /** @var CustomFieldEntity $attribute */
        foreach ($result as $attribute) {
            $this->customFields[$attribute->getName()] = $attribute;
        }

        return $this->customFields;
    }
}
