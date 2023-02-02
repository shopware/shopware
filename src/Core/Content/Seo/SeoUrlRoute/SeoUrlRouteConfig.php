<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class SeoUrlRouteConfig
{
    /**
     * @var EntityDefinition
     */
    private $definition;

    /**
     * @var string
     */
    private $routeName;

    /**
     * @var string
     */
    private $template;

    /**
     * @var bool
     */
    private $skipInvalid;

    public function __construct(EntityDefinition $definition, string $routeName, string $defaultTemplate, bool $skipInvalid = true)
    {
        $this->definition = $definition;
        $this->routeName = $routeName;
        $this->template = $defaultTemplate;
        $this->skipInvalid = $skipInvalid;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    public function getSkipInvalid(): bool
    {
        return $this->skipInvalid;
    }

    public function setSkipInvalid(bool $skipInvalid): void
    {
        $this->skipInvalid = $skipInvalid;
    }
}
