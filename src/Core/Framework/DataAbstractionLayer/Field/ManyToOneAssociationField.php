<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToOneAssociationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer;

class ManyToOneAssociationField extends AssociationField
{
    public const PRIORITY = 80;

    /**
     * @var string
     */
    protected $storageName;

    public function __construct(
        string $propertyName,
        string $storageName,
        string $referenceClass,
        string $referenceField = 'id',
        bool $autoload = false
    ) {
        parent::__construct($propertyName);

        $this->referenceClass = $referenceClass;
        $this->storageName = $storageName;
        $this->referenceField = $referenceField;
        $this->autoload = $autoload;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getExtractPriority(): int
    {
        return self::PRIORITY;
    }

    protected function getSerializerClass(): string
    {
        return ManyToOneAssociationFieldSerializer::class;
    }

    protected function getResolverClass(): ?string
    {
        return ManyToOneAssociationFieldResolver::class;
    }
}
