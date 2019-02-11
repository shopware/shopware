<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Defaults;
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
    protected $versionId;

    /**
     * @var SourceContext
     */
    protected $sourceContext;

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

    public function __construct(
        SourceContext $sourceContext,
        array $rules = [],
        string $currencyId = Defaults::CURRENCY,
        array $languageIdChain = [Defaults::LANGUAGE_SYSTEM],
        string $versionId = Defaults::LIVE_VERSION,
        float $currencyFactor = 1.0
    ) {
        $this->sourceContext = $sourceContext;
        $this->rules = $rules;
        $this->currencyId = $currencyId;

        $this->versionId = $versionId;
        $this->currencyFactor = $currencyFactor;

        if (empty($languageIdChain)) {
            throw new \InvalidArgumentException('languageIdChain may not be empty');
        }
        $this->languageIdChain = array_keys(array_flip(array_filter($languageIdChain)));
    }

    public static function createDefaultContext(): self
    {
        return new self(new SourceContext());
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
            $this->rules,
            $this->currencyId,
            $this->languageIdChain,
            $versionId,
            $this->currencyFactor
        );

        foreach ($this->getExtensions() as $key => $extension) {
            $context->addExtension($key, $extension);
        }

        return $context;
    }
}
