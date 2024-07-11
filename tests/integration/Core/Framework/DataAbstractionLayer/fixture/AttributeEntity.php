<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\AutoIncrement;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Serialized;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldType\DateInterval;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

/**
 * @internal
 */
#[Entity('attribute_entity', since: '6.6.3.0')]
class AttributeEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Field(type: FieldType::STRING)]
    public string $string;

    #[Field(type: FieldType::TEXT)]
    public ?string $text = null;

    #[Field(type: FieldType::INT)]
    public ?int $int;

    #[Field(type: FieldType::FLOAT)]
    public ?float $float;

    #[Field(type: FieldType::BOOL)]
    public ?bool $bool;

    #[Field(type: FieldType::DATETIME)]
    public ?\DateTimeImmutable $datetime = null;

    #[AutoIncrement]
    public int $autoIncrement;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON)]
    public ?array $json = null;

    #[Field(type: FieldType::DATE)]
    public ?\DateTimeImmutable $date = null;

    #[Field(type: FieldType::DATE_INTERVAL)]
    public ?DateInterval $dateInterval = null;

    #[Field(type: FieldType::TIME_ZONE)]
    public ?string $timeZone = null;

    #[Serialized(serializer: PriceFieldSerializer::class, api: true)]
    public ?PriceCollection $serialized = null;

    #[Serialized(serializer: PriceFieldSerializer::class, api: true, storageName: 'serialized_storage')]
    public ?PriceCollection $storageSerialized = null;

    #[Required]
    #[Field(type: FieldType::STRING, translated: true)]
    public string $transString;

    #[Field(type: FieldType::TEXT, translated: true)]
    public ?string $transText = null;

    #[Field(type: FieldType::INT, translated: true)]
    public ?int $transInt;

    #[Field(type: FieldType::FLOAT, translated: true)]
    public ?float $transFloat;

    #[Field(type: FieldType::BOOL, translated: true)]
    public ?bool $transBool;

    #[Field(type: FieldType::DATETIME, translated: true)]
    public ?\DateTimeImmutable $transDatetime = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, translated: true)]
    public ?array $transJson = null;

    #[Field(type: FieldType::DATE, translated: true)]
    public ?\DateTimeImmutable $transDate = null;

    #[Field(type: FieldType::DATE_INTERVAL, translated: true)]
    public ?DateInterval $transDateInterval = null;

    #[Field(type: FieldType::TIME_ZONE, translated: true)]
    public ?string $transTimeZone = null;

    #[Field(type: FieldType::STRING, translated: true, storageName: 'string_storage')]
    public ?string $storageString = null;

    #[Field(type: FieldType::TEXT, storageName: 'text_storage')]
    public ?string $storageText = null;

    #[Field(type: FieldType::INT, storageName: 'int_storage')]
    public ?int $storageInt;

    #[Field(type: FieldType::FLOAT, storageName: 'float_storage')]
    public ?float $storageFloat;

    #[Field(type: FieldType::BOOL, storageName: 'bool_storage')]
    public ?bool $storageBool;

    #[Field(type: FieldType::DATETIME, storageName: 'datetime_storage')]
    public ?\DateTimeImmutable $storageDatetime = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, storageName: 'json_storage')]
    public ?array $storageJson = null;

    #[Field(type: FieldType::DATE, storageName: 'date_storage')]
    public ?\DateTimeImmutable $storageDate = null;

    #[Field(type: FieldType::DATE_INTERVAL, storageName: 'date_interval_storage')]
    public ?DateInterval $storageDateInterval = null;

    #[Field(type: FieldType::TIME_ZONE, storageName: 'time_zone_storage')]
    public ?string $storageTimeZone = null;

    #[ForeignKey(entity: 'currency')]
    public ?string $currencyId = null;

    #[ForeignKey(entity: 'currency')]
    public ?string $followId = null;

    #[ManyToOne(entity: 'currency', onDelete: OnDelete::RESTRICT)]
    public ?CurrencyEntity $currency = null;

    #[ManyToOne(entity: 'currency', onDelete: OnDelete::RESTRICT, storageName: 'currency_storage_id')]
    public ?CurrencyEntity $currencyStorage = null;

    #[OneToOne(entity: 'currency', onDelete: OnDelete::SET_NULL)]
    public ?CurrencyEntity $follow = null;

    #[OneToOne(entity: 'currency', storageName: 'follow_storage_id', onDelete: OnDelete::SET_NULL)]
    public ?CurrencyEntity $followStorage = null;

    /**
     * @var array<string, AttributeEntityAgg>|null
     */
    #[OneToMany(entity: 'attribute_entity_agg', ref: 'attribute_entity_id', onDelete: OnDelete::CASCADE)]
    public ?array $aggs = null;

    /**
     * @var array<string, CurrencyEntity>|null
     */
    #[ManyToMany(entity: 'currency', onDelete: OnDelete::CASCADE)]
    public ?array $currencies = null;

    /**
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;
}
