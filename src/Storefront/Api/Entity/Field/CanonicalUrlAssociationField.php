<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Entity\Field;

use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Write\Flag\Extension;
use Shopware\Api\Entity\Write\Flag\ReadOnly;
use Shopware\Storefront\Api\Seo\Definition\SeoUrlDefinition;

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
