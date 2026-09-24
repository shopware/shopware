<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation;

use Shopware\Core\Framework\Adapter\Asset\AssetService;
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
     * The keys cannot be class-string because the legacy classes intentionally have no declarations.
     *
     * @var array<non-empty-string, class-string>
     */
    public const ALIASES = [
        'Shopware\Core\Framework\Plugin\Util\AssetService' => AssetService::class,
        'Shopware\Administration\Controller\NotificationController' => NotificationController::class,
        'Shopware\Administration\Notification\NotificationCollection' => NotificationCollection::class,
        'Shopware\Administration\Notification\NotificationDefinition' => NotificationDefinition::class,
        'Shopware\Administration\Notification\NotificationEntity' => NotificationEntity::class,
        'Shopware\Elasticsearch\Product\SearchConfigLoader' => SearchConfigLoader::class,
    ];

    /**
     * PhpStorm only recognizes explicit class_alias() calls. Keep this method in sync for IDE support;
     * ClassAliasRegistry::ALIASES remains the authoritative list and this method is never called.
     *
     * @codeCoverageIgnore
     */
    // @phpstan-ignore method.unused (PhpStorm indexes these declarations without calling the method.)
    private static function declareAliasesForIde(): void
    {
        class_alias(AssetService::class, 'Shopware\Core\Framework\Plugin\Util\AssetService');
        class_alias(NotificationController::class, 'Shopware\Administration\Controller\NotificationController');
        class_alias(NotificationCollection::class, 'Shopware\Administration\Notification\NotificationCollection');
        class_alias(NotificationDefinition::class, 'Shopware\Administration\Notification\NotificationDefinition');
        class_alias(NotificationEntity::class, 'Shopware\Administration\Notification\NotificationEntity');
        class_alias(SearchConfigLoader::class, 'Shopware\Elasticsearch\Product\SearchConfigLoader');
    }
}
