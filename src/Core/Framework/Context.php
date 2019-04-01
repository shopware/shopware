<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context\AdminApiSource;
use Shopware\Core\Framework\Context\ContextSource;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context\SystemSource;
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
    protected $rules;

    /**
     * @var ContextSource
     */
    protected $source;

    /**
     * @var bool
     */
    protected $considerInheritance;

    public function __construct(
        ContextSource $source,
        array $rules = [],
        string $currencyId = Defaults::CURRENCY,
        array $languageIdChain = [Defaults::LANGUAGE_SYSTEM],
        string $versionId = Defaults::LIVE_VERSION,
        float $currencyFactor = 1.0,
        int $currencyPrecision = 2,
        bool $considerInheritance = false
    ) {
        $this->source = $source;

        if ($source instanceof SystemSource) {
            $this->scope = self::SYSTEM_SCOPE;
        }

        $this->rules = $rules;
        $this->currencyId = $currencyId;

        $this->versionId = $versionId;
        $this->currencyFactor = $currencyFactor;

        if (empty($languageIdChain)) {
            throw new \InvalidArgumentException('Argument languageIdChain must not be empty');
        }
        $this->languageIdChain = array_keys(array_flip(array_filter($languageIdChain)));
        $this->currencyPrecision = $currencyPrecision;
        $this->considerInheritance = $considerInheritance;
    }

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

    public function getRules(): array
    {
        return $this->rules;
    }

    public function getLanguageIdChain(): array
    {
        return $this->languageIdChain;
    }

    public function createWithVersionId(string $versionId): self
    {
        $context = new self(
            $this->source,
            $this->rules,
            $this->currencyId,
            $this->languageIdChain,
            $versionId,
            $this->currencyFactor,
            $this->currencyPrecision
        );

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

    public function getSalesChannelId(): string
    {
        if ($this->source instanceof SalesChannelApiSource) {
            return $this->source->getSalesChannelId();
        }

        return Defaults::SALES_CHANNEL;
    }

    public function getUserId(): ?string
    {
        if ($this->source instanceof AdminApiSource) {
            return $this->source->getUserId();
        }

        return null;
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
}
