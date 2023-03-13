<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing\Helper;

/**
 * @internal
 */
class ExpectedRequest
{
    /**
     * @var string
     */
    public $url;

    /**
     * @var string|null
     */
    public $baseUrl;

    /**
     * @var string|null
     */
    public $domainId;

    /**
     * @var string|null
     */
    public $salesChannelId;

    /**
     * @var bool|null
     */
    public $isStorefrontRequest;

    /**
     * @var string|null
     */
    public $locale;

    /**
     * @var string|null
     */
    public $currency;

    /**
     * @var string|null
     */
    public $languageCode;

    /**
     * @var string|null
     */
    public $snippetLanguageCode;

    /**
     * @var string|null
     */
    public $exception;

    /**
     * @var string|null
     */
    public $resolvedUrl;

    public function __construct(
        string $url,
        ?string $baseUrl,
        ?string $resolvedUrl,
        ?string $domainId,
        ?string $salesChannelId,
        ?bool $isStorefrontRequest,
        ?string $locale,
        ?string $currency,
        ?string $languageCode,
        ?string $snippetLanguageCode,
        ?string $exception = null
    ) {
        $this->url = $url;
        $this->domainId = $domainId;
        $this->salesChannelId = $salesChannelId;
        $this->isStorefrontRequest = $isStorefrontRequest;
        $this->locale = $locale;
        $this->currency = $currency;
        $this->languageCode = $languageCode;
        $this->snippetLanguageCode = $snippetLanguageCode;
        $this->baseUrl = $baseUrl;
        $this->exception = $exception;
        $this->resolvedUrl = $resolvedUrl;
    }
}
