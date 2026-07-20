<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToOneAssociationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToOneAssociationFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class OneToOneAssociationField extends AssociationField
{
    final public const PRIORITY = 80;

    public function __construct(
        string $propertyName,
        protected string $storageName,
        string $referenceField,
        string $referenceClass,
        bool $autoload = true,
    ) {
        parent::__construct($propertyName);

        $this->referenceClass = $referenceClass;
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
        return OneToOneAssociationFieldSerializer::class;
    }

    protected function getResolverClass(): ?string
    {
        return ManyToOneAssociationFieldResolver::class;
    }
}
