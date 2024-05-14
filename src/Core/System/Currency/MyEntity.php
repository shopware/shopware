<?php

namespace Shopware\Core\System\Currency;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Fk;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Primary;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Protection;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Struct\ArrayEntity;

#[Entity(name: 'my_entity')]
class MyEntity extends EntityStruct
{
    #[Primary]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Field(type: FieldType::STRING)]
    public string $number;

    #[Field(type: FieldType::PRICE)]
    public ?Price $price = null;

    #[Required]
    #[Field(type: FieldType::STRING, translated: true, api: true)]
    public ?string $name = null;

    #[Field(type: FieldType::TEXT, translated: true)]
    public ?string $description = null;

    #[Protection(write: ['system'])]
    #[Field(type: FieldType::INT, translated: true)]
    public ?int $position = null;

    #[Field(type: FieldType::FLOAT, translated: true)]
    public ?float $weight = null;

    #[Field(type: FieldType::BOOL, translated: true)]
    public ?bool $highlight = null;

    #[Field(type: FieldType::DATETIME, translated: true)]
    public ?\DateTimeImmutable $release = null;

    #[Fk(entity: 'product')]
    public string $productId;

    #[Fk(entity: 'product')]
    public ?string $followId = null;

    #[ManyToOne(entity: 'product', onDelete: OnDelete::RESTRICT)]
    public ?ProductEntity $product = null;

    #[OneToOne(entity: 'product', onDelete: OnDelete::SET_NULL)]
    public ?ProductEntity $follow = null;

    /**
     * @var array<string,MySub>
     */
    #[OneToMany(entity: 'my_sub', ref: 'my_entity_id', onDelete: OnDelete::CASCADE)]
    public array $subs = [];

    /**
     * @var array<string, CategoryEntity>
     */
    #[ManyToMany(entity: 'category', onDelete: OnDelete::CASCADE)]
    public array $categories = [];

    /**
     * @var array<string, ArrayEntity>
     */
    #[Translations]
    public array $translations;

    /**
     * @var array<string, mixed>
     */
    #[CustomFields]
    public array $customFields;
}
