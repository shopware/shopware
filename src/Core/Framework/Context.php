<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\ProtectionStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class Context extends Struct
{
    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $fallbackLanguageId;

    /**
     * @var string
     */
    protected $snippetSetId;

    /**
     * @var string
     */
    protected $versionId;

    /**
     * @var SourceContext
     */
    protected $sourceContext;

    /**
     * @var array|null
     */
    protected $catalogIds;

    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var float
     */
    protected $currencyFactor;

    /**
     * @var array
     */
    protected $rules;

    /**
     * @var ProtectionStruct
     */
    protected $writeProtection;

    /**
     * @var ProtectionStruct
     */
    protected $deleteProtection;

    public function __construct(
        SourceContext $sourceContext,
        ?array $catalogIds,
        array $rules,
        string $currencyId,
        string $languageId,
        ?string $fallbackLanguageId = null,
        string $versionId = Defaults::LIVE_VERSION,
        float $currencyFactor = 1.0,
        string $snippetSetId = Defaults::SNIPPET_BASE_SET_EN
    ) {
        $this->sourceContext = $sourceContext;
        $this->catalogIds = $catalogIds;
        $this->rules = $rules;
        $this->currencyId = $currencyId;
        $this->languageId = $languageId;
        $this->fallbackLanguageId = $fallbackLanguageId;
        $this->versionId = $versionId;
        $this->currencyFactor = $currencyFactor;
        $this->snippetSetId = $snippetSetId;

        $this->writeProtection = new ProtectionStruct();
        $this->deleteProtection = new ProtectionStruct();
    }

    public static function createDefaultContext(): self
    {
        $sourceContext = new SourceContext('cli');
        $sourceContext->setSalesChannelId(Defaults::SALES_CHANNEL);

        return new self($sourceContext, [Defaults::CATALOG], [], Defaults::CURRENCY, Defaults::LANGUAGE_EN);
    }

    public static function createFromSalesChannel(SalesChannelEntity $salesChannel, string $origin): self
    {
        $sourceContext = new SourceContext($origin);
        $sourceContext->setSalesChannelId($salesChannel->getId());

        return new self(
            $sourceContext,
            $salesChannel->getCatalogs()->getIds(),
            [],
            $salesChannel->getCurrencyId(),
            $salesChannel->getLanguageId(),
            $salesChannel->getLanguage()->getParentId(),
            Defaults::LIVE_VERSION,
            $salesChannel->getCurrency()->getFactor()
        );
    }

    public function hasFallback(): bool
    {
        return $this->getFallbackLanguageId() !== null
            && $this->getFallbackLanguageId() !== $this->getLanguageId();
    }

    public function getSourceContext(): SourceContext
    {
        return $this->sourceContext;
    }

    public function getVersionId(): string
    {
        return $this->versionId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function getCatalogIds(): ?array
    {
        return $this->catalogIds;
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

    public function getFallbackLanguageId(): ?string
    {
        return $this->fallbackLanguageId;
    }

    public function createWithVersionId(string $versionId): self
    {
        $context = new self(
            $this->sourceContext,
            $this->catalogIds,
            $this->rules,
            $this->currencyId,
            $this->languageId,
            $this->fallbackLanguageId,
            $versionId,
            $this->currencyFactor,
            $this->snippetSetId
        );

        foreach ($this->getExtensions() as $key => $extension) {
            $context->addExtension($key, $extension);
        }
        $context->getWriteProtection()->allow(
            ...$this->getWriteProtection()->all()
        );

        return $context;
    }

    public function createWithCatalogIds(array $catalogIds): self
    {
        $context = new self(
            $this->sourceContext,
            $catalogIds,
            $this->rules,
            $this->currencyId,
            $this->languageId,
            $this->fallbackLanguageId,
            $this->versionId,
            $this->currencyFactor,
            $this->snippetSetId
        );

        foreach ($this->getExtensions() as $key => $extension) {
            $context->addExtension($key, $extension);
        }
        $context->getWriteProtection()->allow(
            ...$this->getWriteProtection()->all()
        );

        return $context;
    }

    public function getWriteProtection(): ProtectionStruct
    {
        return $this->writeProtection;
    }

    public function getDeleteProtection(): ProtectionStruct
    {
        return $this->deleteProtection;
    }

    /**
     * @return string[]
     */
    public function getLanguageIdChain(): array
    {
        if ($this->hasFallback()) {
            $root = $this->getFallbackLanguageId();
            $sub = $this->getLanguageId();

            return [Defaults::LANGUAGE_EN, $root, $sub];
        }

        $root = $this->getLanguageId();
        if ($root !== Defaults::LANGUAGE_EN) {
            return [Defaults::LANGUAGE_EN, $root];
        }

        return [Defaults::LANGUAGE_EN];
    }

    public function getSnippetSetId(): string
    {
        return $this->snippetSetId;
    }
}
