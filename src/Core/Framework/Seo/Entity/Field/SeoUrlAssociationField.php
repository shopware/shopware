<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Seo\Entity\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\Seo\Entity\Dbal\SeoUrlAssociationFieldResolver;
use Shopware\Core\Framework\Seo\Entity\Serializer\SeoUrlFieldSerializer;
use Shopware\Core\Framework\Seo\SeoUrl\SeoUrlDefinition;

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
