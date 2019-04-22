<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AttributeService implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var AttributeEntity[]|null
     */
    private $attributes;

    public function __construct(EntityRepositoryInterface $attributeRepository)
    {
        $this->attributeRepository = $attributeRepository;
    }

    public function getAttributeField(string $attributeName): Field
    {
        /** @var AttributeEntity|null $attribute */
        $attribute = $this->getAttributes()[$attributeName] ?? null;
        if (!$attribute) {
            return new JsonField($attributeName, $attributeName);
        }

        switch ($attribute->getType()) {
            case AttributeTypes::INT:
                return new IntField($attributeName, $attributeName);

            case AttributeTypes::FLOAT:
                return new FloatField($attributeName, $attributeName);

            case AttributeTypes::BOOL:
                return new BoolField($attributeName, $attributeName);

            case AttributeTypes::DATETIME:
                return new DateField($attributeName, $attributeName);

            case AttributeTypes::TEXT:
                return new LongTextField($attributeName, $attributeName);

            case AttributeTypes::HTML:
                return new LongTextWithHtmlField($attributeName, $attributeName);

            case AttributeTypes::JSON:
            default:
                return new JsonField($attributeName, $attributeName);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AttributeEvents::ATTRIBUTE_DELETED_EVENT => 'invalidateCache',
            AttributeEvents::ATTRIBUTE_WRITTEN_EVENT => 'invalidateCache',
        ];
    }

    /**
     * @internal
     */
    public function invalidateCache(): void
    {
        $this->attributes = null;
    }

    /**
     * @return AttributeEntity[]
     */
    private function getAttributes(): array
    {
        if ($this->attributes !== null) {
            return $this->attributes;
        }

        $this->attributes = [];
        // attributes should not be context dependent
        $result = $this->attributeRepository->search(new Criteria(), Context::createDefaultContext());
        /** @var AttributeEntity $attribute */
        foreach ($result as $attribute) {
            $this->attributes[$attribute->getName()] = $attribute;
        }

        return $this->attributes;
    }
}
