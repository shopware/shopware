<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity as EntityAttribute;

#[EntityAttribute('consent', )]
class ConsentEntity extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Field(type: FieldType::STRING)]
    public string $name;

    #[Field(type: FieldType::STRING)]
    public string $type;

    #[Field(type: FieldType::STRING, column: 'required_permissions')]
    public string $requiredPermissions;

    /**
     * @var array<string, ConsentHistoryEntity>
     */
    #[OneToMany(entity: 'consent_history', ref: 'consentId')]
    public array $history = [];
}