<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing\Helper;

/**
 * @internal
 */
class ExpectedRequest
{
    public string $url;

    public ?string $baseUrl;

    public ?string $domainId;

    public ?string $salesChannelId;

    public ?bool $isStorefrontRequest;

    public ?string $locale;

    public ?string $currency;

    public ?string $languageCode;

    public ?string $snippetLanguageCode;

    /**
     * @var class-string<\Throwable>|null
     */
    public $exception;

    public ?string $resolvedUrl;

    /**
     * @param class-string<\Throwable>|null $exception
     */
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
