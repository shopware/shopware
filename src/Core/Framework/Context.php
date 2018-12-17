<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\ProtectionStruct;
use Shopware\Core\Framework\Struct\Struct;

class Context extends Struct
{
    /**
     * @var string[]
     */
    protected $languageIdChain;

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
        ?array $catalogIds = [Defaults::CATALOG],
        array $rules = [],
        string $currencyId = Defaults::CURRENCY,
        array $languageIdChain = [Defaults::LANGUAGE_SYSTEM],
        string $versionId = Defaults::LIVE_VERSION,
        float $currencyFactor = 1.0,
        string $snippetSetId = Defaults::SNIPPET_BASE_SET_EN
    ) {
        $this->sourceContext = $sourceContext;
        $this->catalogIds = $catalogIds;
        $this->rules = $rules;
        $this->currencyId = $currencyId;

        $this->versionId = $versionId;
        $this->currencyFactor = $currencyFactor;
        $this->snippetSetId = $snippetSetId;

        if (empty($languageIdChain)) {
            throw new \InvalidArgumentException('languageIdChain may not be empty');
        }
        $this->languageIdChain = array_unique(array_filter(array_values($languageIdChain)));

        $this->writeProtection = new ProtectionStruct();
        $this->deleteProtection = new ProtectionStruct();
    }

    public static function createDefaultContext(): self
    {
        return new self(new SourceContext('cli'));
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
        return $this->languageIdChain[0];
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

    public function getLanguageIdChain(): array
    {
        return $this->languageIdChain;
    }

    public function createWithVersionId(string $versionId): self
    {
        $context = new self(
            $this->sourceContext,
            $this->catalogIds,
            $this->rules,
            $this->currencyId,
            $this->languageIdChain,
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
            $this->languageIdChain,
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

    public function getSnippetSetId(): string
    {
        return $this->snippetSetId;
    }
}
