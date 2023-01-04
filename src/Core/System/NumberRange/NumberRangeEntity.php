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
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string|null
     */
    protected $typeId;

    /**
     * @var bool
     */
    protected $global;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $pattern;

    /**
     * @var int|null
     */
    protected $start;

    /**
     * @var NumberRangeTypeEntity|null
     */
    protected $type;

    /**
     * @var NumberRangeSalesChannelCollection|null
     */
    protected $numberRangeSalesChannels;

    /**
     * @var NumberRangeStateEntity|null
     */
    protected $state;

    /**
     * @var NumberRangeTranslationCollection|null
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
