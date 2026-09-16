<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation;

use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\Api\NotificationController;
use Shopware\Core\Framework\Notification\NotificationCollection;
use Shopware\Core\Framework\Notification\NotificationDefinition;
use Shopware\Core\Framework\Notification\NotificationEntity;

/**
 * @internal
 */
#[Package('framework')]
final class ClassAliasRegistry
{
    /**
     * @var array<non-empty-string, class-string>
     */
    public const ALIASES = [
        'Shopware\Administration\Controller\NotificationController' => NotificationController::class,
        'Shopware\Administration\Notification\NotificationCollection' => NotificationCollection::class,
        'Shopware\Administration\Notification\NotificationDefinition' => NotificationDefinition::class,
        'Shopware\Administration\Notification\NotificationEntity' => NotificationEntity::class,
        'Shopware\Elasticsearch\Product\SearchConfigLoader' => SearchConfigLoader::class,
    ];

    private function __construct()
    {
    }
}
