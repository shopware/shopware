<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class ErrorTemplateStruct extends Struct
{
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

    public function getApiAlias(): string
    {
        return 'twig_error_template';
    }

    public function isErrorPage(): bool
    {
        return true;
    }
}
