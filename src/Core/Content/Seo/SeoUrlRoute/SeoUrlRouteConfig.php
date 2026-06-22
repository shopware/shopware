<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('inventory')]
class SeoUrlRouteConfig
{
    /**
     * @param \Closure(SalesChannelEntity, ArrayStruct): string|null $routeBySalesChannelGetter
     */
    public function __construct(
        private readonly EntityDefinition $definition,
        private readonly string $routeName,
        private string $template,
        private bool $skipInvalid = true,
        private readonly ?\Closure $routeBySalesChannelGetter = null,
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

    public function getRouteBySalesChannel(
        SalesChannelEntity $salesChannelEntity,
        ArrayStruct $parameters = new ArrayStruct()
    ): string {
        if ($this->routeBySalesChannelGetter) {
            return ($this->routeBySalesChannelGetter)($salesChannelEntity, $parameters);
        }

        return $this->getRouteName();
    }
}
