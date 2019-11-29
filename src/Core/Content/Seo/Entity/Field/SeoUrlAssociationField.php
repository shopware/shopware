<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Entity\Field;

use Shopware\Core\Content\Seo\Entity\Dbal\SeoUrlAssociationFieldResolver;
use Shopware\Core\Content\Seo\Entity\Serializer\SeoUrlFieldSerializer;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;

class SeoUrlAssociationField extends OneToManyAssociationField
{
    /**
     * @var string
     */
    private $routeName;

    public function __construct(
        string $propertyName,
        string $routeName,
        string $localField = 'id'
    ) {
        parent::__construct($propertyName, SeoUrlDefinition::class, 'foreign_key', $localField);
        $this->addFlags(new Extension());
        $this->routeName = $routeName;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    protected function getSerializerClass(): string
    {
        return SeoUrlFieldSerializer::class;
    }

    protected function getResolverClass(): string
    {
        return SeoUrlAssociationFieldResolver::class;
    }
}
