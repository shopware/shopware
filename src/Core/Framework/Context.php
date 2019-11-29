<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Struct\Struct;

class Context extends Struct
{
    public const SYSTEM_SCOPE = 'system';
    public const USER_SCOPE = 'user';

    /**
     * @var string[]
     */
    protected $languageIdChain;

    /**
     * @var string
     */
    protected $versionId;

    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var float
     */
    protected $currencyFactor;

    /**
     * @var int
     */
    protected $currencyPrecision;

    /**
     * @var string
     */
    protected $scope = self::USER_SCOPE;

    /**
     * @var array
     */
    protected $ruleIds;

    /**
     * @var ContextSource
     */
    protected $source;

    /**
     * @var bool
     */
    protected $considerInheritance;

    /**
     * @see CartPrice::TAX_STATE_GROSS, CartPrice::TAX_STATE_NET, CartPrice::TAX_STATE_FREE
     *
     * @var string
     */
    protected $taxState = CartPrice::TAX_STATE_GROSS;

    /**
     * @var bool
     */
    private $useCache = true;

    public function __construct(
        ContextSource $source,
        array $ruleIds = [],
        string $currencyId = Defaults::CURRENCY,
        array $languageIdChain = [Defaults::LANGUAGE_SYSTEM],
        string $versionId = Defaults::LIVE_VERSION,
        float $currencyFactor = 1.0,
        int $currencyPrecision = 2,
        bool $considerInheritance = false,
        string $taxState = CartPrice::TAX_STATE_GROSS
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
        $this->currencyPrecision = $currencyPrecision;
        $this->considerInheritance = $considerInheritance;
        $this->taxState = $taxState;
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
            $this->currencyPrecision,
            $this->considerInheritance,
            $this->taxState
        );
        $context->scope = $this->scope;

        foreach ($this->getExtensions() as $key => $extension) {
            $context->addExtension($key, $extension);
        }

        return $context;
    }

    public function scope(string $scope, callable $callback): void
    {
        $currentScope = $this->getScope();
        $this->scope = $scope;

        $callback($this);

        $this->scope = $currentScope;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getCurrencyPrecision(): int
    {
        return $this->currencyPrecision;
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

    public function disableCache(callable $function)
    {
        $this->useCache = false;
        $result = $function($this);
        $this->useCache = true;

        return $result;
    }

    public function getUseCache(): bool
    {
        return $this->useCache;
    }

    public function isAllowed(string $resource, string $privilege): bool
    {
        if ($this->source instanceof AdminApiSource) {
            return $this->source->isAllowed($resource, $privilege);
        }

        return true;
    }

    public function setRuleIds(array $ruleIds): void
    {
        $this->ruleIds = array_filter(array_values($ruleIds));
    }
}
