<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class SeoUrlRouteConfig
{
    public function __construct(
        private readonly EntityDefinition $definition,
        private readonly string $routeName,
        private string $template,
        private bool $skipInvalid = true,
        private readonly ?string $primaryKeyParameterKey = null,
    ) {
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

    /**
     * @return array<string, string>
     */
    public function getPrimaryKeyParameter(string $primaryKey): array
    {
        if ($this->primaryKeyParameterKey === null) {
            throw SeoUrlRouteConfigException::routeConfigMissingParameterKeyForPrimaryKey($this->definition->getEntityName());
        }

        return [$this->primaryKeyParameterKey => $primaryKey];
    }
}
