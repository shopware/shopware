<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsException;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('content')]
class CmsPageDefaultChangeSubscriber implements EventSubscriberInterface
{
    /**
     * @var array<string>
     */
    public static array $defaultCmsPageConfigKeys = [
        ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT,
        CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY,
    ];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeSystemConfigChangedEvent::class => 'validateChangeOfDefaultCmsPage',
            BeforeDeleteEvent::class => 'beforeDeletion',
        ];
    }

    /**
     * @throws CmsException
     * @throws \JsonException
     */
    public function beforeDeletion(BeforeDeleteEvent $event): void
    {
        $cmsPageIds = $event->getIds(CmsPageDefinition::ENTITY_NAME);

        // no cms page is affected by this deletion event
        if (empty($cmsPageIds)) {
            return;
        }

        $defaultPages = $this->cmsPageIsDefault($cmsPageIds);

        // count !== 0 indicates that there are some cms pages which would be deleted but are currently a default
        if (\count($defaultPages) !== 0) {
            throw CmsException::deletionOfDefault($defaultPages);
        }
    }

    /**
     * @throws CmsException
     * @throws PageNotFoundException
     */
    public function validateChangeOfDefaultCmsPage(BeforeSystemConfigChangedEvent $event): void
    {
        $newDefaultCmsPageId = $event->getValue();
        $systemConfigKey = $event->getKey();
        $salesChannelId = $event->getSalesChannelId();

        if (!\in_array($systemConfigKey, self::$defaultCmsPageConfigKeys, true)) {
            return;
        }

        // prevent deleting the overall default (salesChannelId === null)
        // a sales channel specific default can still be deleted (salesChannelId !== null)
        if ($newDefaultCmsPageId === null && $salesChannelId === null) {
            $oldCmsPageId = $this->getCurrentOverallDefaultCmsPageId($systemConfigKey);

            throw CmsException::overallDefaultSystemConfigDeletion($oldCmsPageId);
        }

        if (!\is_string($newDefaultCmsPageId) && $newDefaultCmsPageId !== null) {
            throw new PageNotFoundException('invalid page');
        }

        // prevent changing the default to an invalid cms page id
        if (\is_string($newDefaultCmsPageId) && !$this->cmsPageExists($newDefaultCmsPageId)) {
            throw new PageNotFoundException($newDefaultCmsPageId);
        }
    }

    private function getCurrentOverallDefaultCmsPageId(string $systemConfigKey): string
    {
        $result = $this->connection->fetchOne(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :configKey AND sales_channel_id is NULL;',
            [
                'configKey' => $systemConfigKey,
            ]
        );

        $config = json_decode((string) $result, true, 512, \JSON_THROW_ON_ERROR);

        return $config['_value'];
    }

    /**
     * @param array<string> $cmsPageIds
     *
     * @return array<string>
     */
    private function cmsPageIsDefault(array $cmsPageIds): array
    {
        $configurations = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT configuration_value FROM system_config WHERE configuration_key IN (:configKeys);',
            [
                'configKeys' => self::$defaultCmsPageConfigKeys,
            ],
            [
                'configKeys' => ArrayParameterType::STRING,
            ]
        );

        $defaultIds = [];
        foreach ($configurations as $configuration) {
            $configValue = $configuration['configuration_value'];
            $config = json_decode((string) $configValue, true, 512, \JSON_THROW_ON_ERROR);

            $defaultIds[] = $config['_value'];
        }

        // returns from all provided cms pages the ones which are default
        return array_intersect($cmsPageIds, $defaultIds);
    }

    private function cmsPageExists(string $cmsPageId): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT count(*) FROM cms_page WHERE id = :cmsPageId AND version_id = :versionId LIMIT 1;',
            [
                'cmsPageId' => Uuid::fromHexToBytes($cmsPageId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        return $count === '1';
    }
}
