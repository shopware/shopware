<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\Framework\Notification\Api\NotificationController;
use Shopware\Core\Framework\Notification\NotificationCollection;
use Shopware\Core\Framework\Notification\NotificationDefinition;
use Shopware\Core\Framework\Notification\NotificationEntity;

/**
 * Runs while Composer initializes, before PHPUnit can start collecting coverage.
 * Keep the aliases explicit so IDEs can index the previous class names.
 */
// @codeCoverageIgnoreStart
if (!class_exists('Shopware\Administration\Controller\NotificationController', autoload: false)) {
    class_alias(NotificationController::class, 'Shopware\Administration\Controller\NotificationController');
}

if (!class_exists('Shopware\Administration\Notification\NotificationCollection', autoload: false)) {
    class_alias(NotificationCollection::class, 'Shopware\Administration\Notification\NotificationCollection');
}

if (!class_exists('Shopware\Administration\Notification\NotificationDefinition', autoload: false)) {
    class_alias(NotificationDefinition::class, 'Shopware\Administration\Notification\NotificationDefinition');
}

if (!class_exists('Shopware\Administration\Notification\NotificationEntity', autoload: false)) {
    class_alias(NotificationEntity::class, 'Shopware\Administration\Notification\NotificationEntity');
}

if (!class_exists('Shopware\Elasticsearch\Product\SearchConfigLoader', autoload: false)) {
    class_alias(SearchConfigLoader::class, 'Shopware\Elasticsearch\Product\SearchConfigLoader');
}
// @codeCoverageIgnoreEnd
