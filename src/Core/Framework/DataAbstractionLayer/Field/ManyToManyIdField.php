<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;

class ManyToManyIdField extends ListField
{
    /**
     * @var string
     */
    private $associationName;

    public function __construct(
        string $storageName,
        string $propertyName,
        string $associationName
    ) {
        parent::__construct($storageName, $propertyName, IdField::class);
        $this->associationName = $associationName;
        $this->addFlags(new WriteProtected());
    }

    public function getAssociationName(): string
    {
        return $this->associationName;
    }
}
