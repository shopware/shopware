<?php declare(strict_types=1);

namespace Shopware\Storefront\Seo\Entity\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Storefront\Seo\SeoUrlDefinition;

class CanonicalUrlAssociationField extends ManyToOneAssociationField
{
    /**
     * @var string
     */
    private $routeName;

    public function __construct(
        string $propertyName,
        string $storageName,
        bool $loadInBasic,
        string $routeName
    ) {
        parent::__construct($propertyName, $storageName, SeoUrlDefinition::class, $loadInBasic, 'foreign_key');
        $this->setFlags(new ReadOnly(), new Extension());
        $this->routeName = $routeName;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }
}
