<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Struct\StateAwareTrait;
use Shopware\Core\Framework\Struct\Struct;

class Context extends Struct
{
    use StateAwareTrait;

    public const SYSTEM_SCOPE = 'system';
    public const USER_SCOPE = 'user';
    public const CRUD_API_SCOPE = 'crud';
    public const STATE_ELASTICSEARCH_AWARE = 'elasticsearchAware';
    public const SKIP_TRIGGER_FLOW = 'skipTriggerFlow';

    /**
     * @var string[]
     *
     * @deprecated tag:v6.5.0 prop will be natively typed as `array` in future versions
     */
    protected $languageIdChain;

    /**
     * @var string
     *
     * @deprecated tag:v6.5.0 prop will be natively typed as `string` in future versions
     */
    protected $versionId;

    /**
     * @var string
     *
     * @deprecated tag:v6.5.0 prop will be natively typed as `string` in future versions
     */
    protected $currencyId;

    /**
     * @var float
     *
     * @deprecated tag:v6.5.0 prop will be natively typed as `float` in future versions
     */
    protected $currencyFactor;

    /**
     * @var string
     *
     * @deprecated tag:v6.5.0 prop will be natively typed as `string` in future versions
     */
    protected $scope = self::USER_SCOPE;

    /**
     * @var array
     *
     * @deprecated tag:v6.5.0 prop will be natively typed as `array` in future versions
     */
    protected $ruleIds;

    /**
     * @var ContextSource
     *
     * @deprecated tag:v6.5.0 prop will be natively typed as `ContextSource` in future versions
     */
    protected $source;

    /**
     * @var bool
     *
     * @deprecated tag:v6.5.0 prop will be natively typed as `bool` in future versions
     */
    protected $considerInheritance;

    /**
     * @see CartPrice::TAX_STATE_GROSS, CartPrice::TAX_STATE_NET, CartPrice::TAX_STATE_FREE
     *
     * @var string
     *
     * @deprecated tag:v6.5.0 prop will be natively typed as `string` in future versions
     */
    protected $taxState = CartPrice::TAX_STATE_GROSS;

    /**
     * @var CashRoundingConfig
     *
     * @deprecated tag:v6.5.0 prop will be natively typed as `CashRoundingConfig` in future versions
     */
    protected $rounding;

    public function __construct(
        ContextSource $source,
        array $ruleIds = [],
        string $currencyId = Defaults::CURRENCY,
        array $languageIdChain = [Defaults::LANGUAGE_SYSTEM],
        string $versionId = Defaults::LIVE_VERSION,
        float $currencyFactor = 1.0,
        bool $considerInheritance = false,
        string $taxState = CartPrice::TAX_STATE_GROSS,
        ?CashRoundingConfig $rounding = null
    ) {
        $this->source = $source;

        if ($source instanceof SystemSource) {
            $this->scope = self::SYSTEM_SCOPE;
        }

        $this->ruleIds = $ruleIds;
        $this->currencyId = $currencyId;

        $this->versionId = $versionId;
        $this->currencyFactor = $currencyFactor;

        if (empty($languageIdChain)) {
            throw new \InvalidArgumentException('Argument languageIdChain must not be empty');
        }
        $this->languageIdChain = array_keys(array_flip(array_filter($languageIdChain)));
        $this->considerInheritance = $considerInheritance;
        $this->taxState = $taxState;
        $this->rounding = $rounding ?? new CashRoundingConfig(2, 0.01, true);
    }

    /**
     * @internal
     */
    public static function createDefaultContext(?ContextSource $source = null): self
    {
        $source = $source ?? new SystemSource();

        return new self($source);
    }

    public function getSource(): ContextSource
    {
        return $this->source;
    }

    public function getVersionId(): string
    {
        return $this->versionId;
    }

    public function getLanguageId(): string
    {
        return $this->languageIdChain[0];
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function getCurrencyFactor(): float
    {
        return $this->currencyFactor;
    }

    public function getRuleIds(): array
    {
        return $this->ruleIds;
    }

    public function getLanguageIdChain(): array
    {
        return $this->languageIdChain;
    }

    public function createWithVersionId(string $versionId): self
    {
        $context = new self(
            $this->source,
            $this->ruleIds,
            $this->currencyId,
            $this->languageIdChain,
            $versionId,
            $this->currencyFactor,
            $this->considerInheritance,
            $this->taxState,
            $this->rounding
        );
        $context->scope = $this->scope;

        foreach ($this->getExtensions() as $key => $extension) {
            $context->addExtension($key, $extension);
        }

        return $context;
    }

    /**
     * @return mixed the return value of the provided callback function
     */
    public function scope(string $scope, callable $callback)
    {
        $currentScope = $this->getScope();
        $this->scope = $scope;

        try {
            $result = $callback($this);
        } finally {
            $this->scope = $currentScope;
        }

        return $result;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function considerInheritance(): bool
    {
        return $this->considerInheritance;
    }

    public function setConsiderInheritance(bool $considerInheritance): void
    {
        $this->considerInheritance = $considerInheritance;
    }

    public function getTaxState(): string
    {
        return $this->taxState;
    }

    public function setTaxState(string $taxState): void
    {
        $this->taxState = $taxState;
    }

    public function isAllowed(string $privilege): bool
    {
        if ($this->source instanceof AdminApiSource) {
            return $this->source->isAllowed($privilege);
        }

        return true;
    }

    public function setRuleIds(array $ruleIds): void
    {
        $this->ruleIds = array_filter(array_values($ruleIds));
    }

    public function enableInheritance(callable $function)
    {
        $previous = $this->considerInheritance;
        $this->considerInheritance = true;
        $result = $function($this);
        $this->considerInheritance = $previous;

        return $result;
    }

    public function disableInheritance(callable $function)
    {
        $previous = $this->considerInheritance;
        $this->considerInheritance = false;
        $result = $function($this);
        $this->considerInheritance = $previous;

        return $result;
    }

    public function getApiAlias(): string
    {
        return 'context';
    }

    public function getRounding(): CashRoundingConfig
    {
        return $this->rounding;
    }

    public function setRounding(CashRoundingConfig $rounding): void
    {
        $this->rounding = $rounding;
    }
}
