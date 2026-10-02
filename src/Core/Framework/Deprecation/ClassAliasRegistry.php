<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation;

use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentDefinition;
use Shopware\Core\Checkout\DocumentV2\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\SalesChannel\AbstractDocumentRoute;
use Shopware\Core\Checkout\DocumentV2\SalesChannel\DocumentRoute;
use Shopware\Core\Checkout\DocumentV2\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderedDocument;
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
        'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection' => DocumentBaseConfigCollection::class,
        'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition' => DocumentBaseConfigDefinition::class,
        'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity' => DocumentBaseConfigEntity::class,
        'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection' => DocumentBaseConfigSalesChannelCollection::class,
        'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition' => DocumentBaseConfigSalesChannelDefinition::class,
        'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity' => DocumentBaseConfigSalesChannelEntity::class,
        'Shopware\Core\Checkout\Document\DocumentCollection' => DocumentCollection::class,
        'Shopware\Core\Checkout\Document\DocumentDefinition' => DocumentDefinition::class,
        'Shopware\Core\Checkout\Document\DocumentEntity' => DocumentEntity::class,
        'Shopware\Core\Checkout\Document\Renderer\RenderedDocument' => RenderedDocument::class,
        'Shopware\Core\Checkout\Document\SalesChannel\AbstractDocumentRoute' => AbstractDocumentRoute::class,
        'Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute' => DocumentRoute::class,
        'Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader' => ReferenceInvoiceLoader::class,
        'Shopware\Core\Framework\Plugin\Util\AssetService' => AssetService::class,
        'Shopware\Administration\Controller\NotificationController' => NotificationController::class,
        'Shopware\Administration\Notification\NotificationCollection' => NotificationCollection::class,
        'Shopware\Administration\Notification\NotificationDefinition' => NotificationDefinition::class,
        'Shopware\Administration\Notification\NotificationEntity' => NotificationEntity::class,
        'Shopware\Elasticsearch\Product\SearchConfigLoader' => SearchConfigLoader::class,
    ];

    /**
     * @var array<non-empty-string, class-string>
     */
    private static array $packageAliases = [];

    /**
     * Registers aliases contributed by an installed extension from its Composer autoload file.
     *
     * @param array<non-empty-string, class-string> $aliases
     */
    public static function registerAliases(array $aliases): void
    {
        foreach ($aliases as $previousClassName => $currentClassName) {
            $registeredClassName = self::canonicalClassName($previousClassName);
            if ($registeredClassName !== null && $registeredClassName !== $currentClassName) {
                // @phpstan-ignore shopware.domainException (Composer bootstrap validates conflicting alias declarations.)
                throw new \LogicException(\sprintf(
                    'Cannot register class alias "%s" to "%s": the alias is already registered for "%s".',
                    $previousClassName,
                    $currentClassName,
                    $registeredClassName
                ));
            }

            if ($registeredClassName === null) {
                self::$packageAliases[$previousClassName] = $currentClassName;
            }

            self::registerAlias($previousClassName, $currentClassName);
        }
    }

    /**
     * @return array<non-empty-string, class-string>
     */
    public static function aliases(): array
    {
        return self::ALIASES + self::$packageAliases;
    }

    private static function canonicalClassName(string $previousClassName): ?string
    {
        foreach (self::aliases() as $registeredPreviousClassName => $registeredClassName) {
            if (strtolower($registeredPreviousClassName) === strtolower($previousClassName)) {
                return $registeredClassName;
            }
        }

        return null;
    }

    /**
     * @param non-empty-string $previousClassName
     * @param class-string $currentClassName
     */
    private static function registerAlias(string $previousClassName, string $currentClassName): void
    {
        if (class_exists($previousClassName, autoload: false)) {
            $registeredClassName = (new \ReflectionClass($previousClassName))->getName();

            if ($registeredClassName !== $currentClassName) {
                // @phpstan-ignore shopware.domainException (Composer bootstrap validates conflicting alias declarations.)
                throw new \LogicException(\sprintf('Cannot register class alias "%s" to "%s": the name already refers to "%s".', $previousClassName, $currentClassName, $registeredClassName));
            }

            return;
        }

        if (!class_exists($currentClassName)) {
            // @phpstan-ignore shopware.domainException (Composer bootstrap validates contributed aliases.)
            throw new \LogicException(\sprintf('Cannot register class alias "%s" to "%s": the canonical class does not exist.', $previousClassName, $currentClassName));
        }

        class_alias($currentClassName, $previousClassName);
    }

    /**
     * PhpStorm only recognizes explicit class_alias() calls. Keep this method in sync for Core IDE support;
     * ClassAliasRegistry::aliases() remains the authoritative list and this method is never called.
     *
     * @codeCoverageIgnore
     */
    // @phpstan-ignore method.unused (PhpStorm indexes these declarations without calling the method.)
    private static function declareAliasesForIde(): void
    {
        class_alias(DocumentBaseConfigCollection::class, 'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection');
        class_alias(DocumentBaseConfigDefinition::class, 'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition');
        class_alias(DocumentBaseConfigEntity::class, 'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity');
        class_alias(DocumentBaseConfigSalesChannelCollection::class, 'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection');
        class_alias(DocumentBaseConfigSalesChannelDefinition::class, 'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition');
        class_alias(DocumentBaseConfigSalesChannelEntity::class, 'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity');
        class_alias(DocumentCollection::class, 'Shopware\Core\Checkout\Document\DocumentCollection');
        class_alias(DocumentDefinition::class, 'Shopware\Core\Checkout\Document\DocumentDefinition');
        class_alias(DocumentEntity::class, 'Shopware\Core\Checkout\Document\DocumentEntity');
        class_alias(RenderedDocument::class, 'Shopware\Core\Checkout\Document\Renderer\RenderedDocument');
        class_alias(AbstractDocumentRoute::class, 'Shopware\Core\Checkout\Document\SalesChannel\AbstractDocumentRoute');
        class_alias(DocumentRoute::class, 'Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute');
        class_alias(ReferenceInvoiceLoader::class, 'Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader');
        class_alias(AssetService::class, 'Shopware\Core\Framework\Plugin\Util\AssetService');
        class_alias(NotificationController::class, 'Shopware\Administration\Controller\NotificationController');
        class_alias(NotificationCollection::class, 'Shopware\Administration\Notification\NotificationCollection');
        class_alias(NotificationDefinition::class, 'Shopware\Administration\Notification\NotificationDefinition');
        class_alias(NotificationEntity::class, 'Shopware\Administration\Notification\NotificationEntity');
        class_alias(SearchConfigLoader::class, 'Shopware\Elasticsearch\Product\SearchConfigLoader');
    }
}
