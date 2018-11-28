<?php declare(strict_types=1);

namespace Shopware\Storefront\Twig;

use Shopware\Core\Framework\Struct\Struct;

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

    public function __construct($templateName = '', $arguments = [])
    {
        $this->templateName = $templateName;
        $this->arguments = $arguments;
    }

    /**
     * @return string
     */
    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    /**
     * @param string $templateName
     */
    public function setTemplateName(string $templateName): void
    {
        $this->templateName = $templateName;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param array $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }
}
