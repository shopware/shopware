<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Footer\FooterPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

class ErrorTemplateStruct extends Struct
{
    /**
     * @var string
     */
    protected $templateName;

    /**
     * @var array
     */
    protected $arguments;

    /**
     * @var HeaderPagelet|null
     */
    protected $header;

    protected ?FooterPagelet $footer = null;

    public function __construct($templateName = '', $arguments = [])
    {
        $this->templateName = $templateName;
        $this->arguments = $arguments;
        $this->header = null;
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    public function setTemplateName(string $templateName): void
    {
        $this->templateName = $templateName;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    public function getHeader(): ?HeaderPagelet
    {
        return $this->header;
    }

    public function setHeader(HeaderPagelet $header): void
    {
        $this->header = $header;
    }

    public function getFooter(): ?FooterPagelet
    {
        return $this->footer;
    }

    public function setFooter(FooterPagelet $footer): void
    {
        $this->footer = $footer;
    }

    public function getApiAlias(): string
    {
        return 'twig_error_template';
    }
}
