<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Events\CmsPageBeforeDefaultChangeEvent;
use Shopware\Core\Content\Cms\Exception\DeletionOfDefaultCmsPageException;
use Shopware\Core\Content\Cms\Exception\DeletionOfOverallDefaultCmsPageException;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CmsPageDefaultChangeSubscriber implements EventSubscriberInterface
{
    public static array $defaultCmsPageConfigKeys = [
        ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT,
        CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY,
    ];

    private Connection $connection;

    private SystemConfigService $systemConfigService;

    private EntityRepository $systemConfigRepository;

    /**
     * @internal
     */
    public function __construct(
        Connection $connection,
        SystemConfigService $systemConfigService,
        EntityRepository $systemConfigRepository
    ) {
        $this->connection = $connection;
        $this->systemConfigService = $systemConfigService;
        $this->systemConfigRepository = $systemConfigRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CmsPageBeforeDefaultChangeEvent::class => 'validateChangeOfDefaultCmsPage',
            BeforeDeleteEvent::class => 'beforeDeletion',
        ];
    }

    /**
     * @throws DeletionOfDefaultCmsPageException
     * @throws \JsonException
     */
    public function beforeDeletion(BeforeDeleteEvent $event): void
    {
        $cmsPageIds = $event->getIds('cms_page');

        // no cms page is affected by this deletion event
        if (empty($cmsPageIds)) {
            return;
        }

        $defaultPages = $this->cmsPageIsDefault($cmsPageIds, $event->getContext());

        // count !== 0 indicates that there are some cms pages which would be deleted but are currently a default
        if (\count($defaultPages) !== 0) {
            $ids = json_encode($defaultPages, \JSON_THROW_ON_ERROR);

            throw new DeletionOfDefaultCmsPageException($ids);
        }
    }

    /**
     * @throws DeletionOfOverallDefaultCmsPageException
     * @throws PageNotFoundException
     */
    public function validateChangeOfDefaultCmsPage(CmsPageBeforeDefaultChangeEvent $event): void
    {
        $newDefaultCmsPageId = $event->getValue();
        $systemConfigKey = $event->getSystemConfigKey();
        $salesChannelId = $event->getSalesChannelId();

        if (!\in_array($systemConfigKey, self::$defaultCmsPageConfigKeys, true)) {
            return;
        }

        // prevent deleting the overall default (salesChannelId === null)
        // a sales channel specific default can still be deleted (salesChannelId !== null)
        if ($newDefaultCmsPageId === null && $salesChannelId === null) {
            $oldCmsPageId = $this->getCurrentOverallDefaultCmsPageId($systemConfigKey);

            throw new DeletionOfOverallDefaultCmsPageException($oldCmsPageId);
        }

        // prevent changing the default to an invalid cms page id
        if ($newDefaultCmsPageId !== null && !$this->cmsPageExists($newDefaultCmsPageId)) {
            throw new PageNotFoundException($newDefaultCmsPageId);
        }
    }

    private function getCurrentOverallDefaultCmsPageId(string $systemConfigKey): string
    {
        return $this->systemConfigService->getString($systemConfigKey, null);
    }

    private function cmsPageIsDefault(array $cmsPageIds, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter(
            'configurationKey',
            self::$defaultCmsPageConfigKeys
        ));

        $results = $this->systemConfigRepository->search($criteria, $context)->getElements();
        $defaultIds = [];

        /** @var SystemConfigEntity $result */
        foreach ($results as $result) {
            $defaultIds[] = $result->getConfigurationValue();
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
