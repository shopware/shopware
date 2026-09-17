<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity(self::ENTITY_NAME, since: '6.7.15.0')]
class AppSeoUrlRouteEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'app_seo_url_route';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[ForeignKey(entity: 'app')]
    public string $appId;

    #[ManyToOne(entity: 'app')]
    public ?AppEntity $app = null;

    #[Field(type: FieldType::STRING)]
    public string $name;

    #[Field(type: FieldType::STRING)]
    public string $routeName;

    #[Field(type: FieldType::STRING)]
    public string $hook;

    #[Field(type: FieldType::STRING, maxLength: 64)]
    public ?string $entityName = null;

    #[Field(type: FieldType::STRING, maxLength: 750)]
    public ?string $defaultTemplate = null;

    /**
     * @var array<string, string>|null
     */
    #[Field(type: FieldType::JSON)]
    public ?array $paths = null;

    /**
     * @var array<string, string>|null
     */
    #[Field(type: FieldType::JSON)]
    public ?array $label = null;
}
