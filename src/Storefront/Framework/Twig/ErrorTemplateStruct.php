<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Footer\FooterPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

#[Package('framework')]
class ErrorTemplateStruct extends Struct
{
    /**
     * @deprecated tag:v6.7.0 - Will be removed, header is loaded via esi and will be rendered in an separate request
     */
    protected ?HeaderPagelet $header = null;

    /**
     * @deprecated tag:v6.7.0 - Will be removed, footer is loaded via esi and will be rendered in an separate request
     */
    protected ?FooterPagelet $footer = null;

    /**
     * @param array<string, \Throwable> $arguments
     */
    public function __construct(
        protected string $templateName = '',
        protected array $arguments = []
    ) {
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    public function setTemplateName(string $templateName): void
    {
        $this->templateName = $templateName;
    }

    /**
     * @return array<string, \Throwable>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param array<string, \Throwable> $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, header is loaded via esi and will be rendered in an separate request
     */
    public function getHeader(): ?HeaderPagelet
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->header;
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, header is loaded via esi and will be rendered in an separate request
     */
    public function setHeader(HeaderPagelet $header): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );
        $this->header = $header;
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, footer is loaded via esi and will be rendered in an separate request
     */
    public function getFooter(): ?FooterPagelet
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->footer;
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, footer is loaded via esi and will be rendered in an separate request
     */
    public function setFooter(FooterPagelet $footer): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );
        $this->footer = $footer;
    }

    public function getApiAlias(): string
    {
        return 'twig_error_template';
    }

    public function isErrorPage(): bool
    {
        return true;
    }
}
