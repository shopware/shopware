<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Entity\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlDefinition;

class CanonicalUrlAssociationField extends ManyToOneAssociationField
{
    /**
     * @var string
     */
    private $routeName;

    public function __construct(
        string $propertyName,
        string $storageName,
        string $routeName,
        bool $autoload = true
    ) {
        parent::__construct($propertyName, $storageName, SeoUrlDefinition::class, 'foreign_key', $autoload);
        $this->addFlags(new WriteProtected(), new Extension());
        $this->routeName = $routeName;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }
}
