<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\OneToManyAssociationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToManyAssociationFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class OneToManyAssociationField extends AssociationField
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $localField;

    public function __construct(
        string $propertyName,
        string $referenceClass,
        string $referenceField,
        string $localField = 'id'
    ) {
        parent::__construct($propertyName);
        $this->localField = $localField;
        $this->referenceField = $referenceField;
        $this->referenceClass = $referenceClass;
    }

    public function getLocalField(): string
    {
        return $this->localField;
    }

    protected function getSerializerClass(): string
    {
        return OneToManyAssociationFieldSerializer::class;
    }

    protected function getResolverClass(): ?string
    {
        return OneToManyAssociationFieldResolver::class;
    }
}
