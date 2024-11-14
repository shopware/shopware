<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelCollection;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateEntity;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeTranslation\NumberRangeTranslationCollection;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeEntity;

#[Package('checkout')]
class NumberRangeEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $typeId;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $global;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $description;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $pattern;

    /**
     * @var int|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $start;

    /**
     * @var NumberRangeTypeEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $type;

    /**
     * @var NumberRangeSalesChannelCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $numberRangeSalesChannels;

    /**
     * @var NumberRangeStateEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $state;

    /**
     * @var NumberRangeTranslationCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $translations;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function setPattern(?string $pattern): void
    {
        $this->pattern = $pattern;
    }

    public function getStart(): ?int
    {
        return $this->start;
    }

    public function setStart(?int $start): void
    {
        $this->start = $start;
    }

    public function getType(): ?NumberRangeTypeEntity
    {
        return $this->type;
    }

    public function setType(?NumberRangeTypeEntity $type): void
    {
        $this->type = $type;
    }

    public function getState(): ?NumberRangeStateEntity
    {
        return $this->state;
    }

    public function setState(?NumberRangeStateEntity $state): void
    {
        $this->state = $state;
    }

    public function getTypeId(): ?string
    {
        return $this->typeId;
    }

    public function setTypeId(?string $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function isGlobal(): bool
    {
        return $this->global;
    }

    public function setGlobal(bool $global): void
    {
        $this->global = $global;
    }

    public function getTranslations(): ?NumberRangeTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(NumberRangeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getNumberRangeSalesChannels(): ?NumberRangeSalesChannelCollection
    {
        return $this->numberRangeSalesChannels;
    }

    public function setNumberRangeSalesChannels(NumberRangeSalesChannelCollection $numberRangeSalesChannels): void
    {
        $this->numberRangeSalesChannels = $numberRangeSalesChannels;
    }
}
