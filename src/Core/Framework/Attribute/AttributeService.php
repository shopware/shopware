<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AttributeService implements AttributeServiceInterface, EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var string[]|null
     */
    private $attributeTypeMap;

    public function __construct(EntityRepositoryInterface $attributeRepository)
    {
        $this->attributeRepository = $attributeRepository;
    }

    public function getAttributeType(string $attributeName): ?string
    {
        return $this->getAttributeTypeMap()[$attributeName] ?? null;
    }

    public function getAttributeTypeMap(): array
    {
        if ($this->attributeTypeMap !== null) {
            return $this->attributeTypeMap;
        }

        $this->attributeTypeMap = [];
        // attributes should not be context dependent
        $result = $this->attributeRepository->search(new Criteria(), Context::createDefaultContext());
        /** @var AttributeEntity $attribute */
        foreach ($result as $attribute) {
            $this->attributeTypeMap[$attribute->getName()] = $attribute->getType();
        }

        return $this->attributeTypeMap;
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
        $this->attributeTypeMap = null;
    }
}
