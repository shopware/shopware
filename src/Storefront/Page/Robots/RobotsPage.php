<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Page\Robots\Struct\DomainRuleCollection;

#[Package('storefront')]
class RobotsPage extends Struct
{
    protected DomainRuleCollection $domainRules;

    /**
     * @var string[]
     */
    protected array $sitemaps;

    public function getDomainRules(): DomainRuleCollection
    {
        return $this->domainRules;
    }

    public function setDomainRules(DomainRuleCollection $domainRules): void
    {
        $this->domainRules = $domainRules;
    }

    /**
     * @return string[]
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * @param string[] $sitemaps
     */
    public function setSitemaps(array $sitemaps): void
    {
        $this->sitemaps = $sitemaps;
    }
}
