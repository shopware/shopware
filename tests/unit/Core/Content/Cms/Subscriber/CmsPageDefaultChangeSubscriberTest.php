<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsException;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\Subscriber\CmsPageDefaultChangeSubscriber;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CmsPageDefaultChangeSubscriber::class)]
class CmsPageDefaultChangeSubscriberTest extends TestCase
{
    public function testHasEvents(): void
    {
        $expectedEvents = [
            BeforeSystemConfigChangedEvent::class => 'validateChangeOfDefaultCmsPage',
            EntityDeleteEvent::class => 'beforeDeletion',
        ];

        static::assertEquals($expectedEvents, CmsPageDefaultChangeSubscriber::getSubscribedEvents());
    }

    /**
     * @param string[] $ids
     */
    #[DataProvider('beforeDeletionEventDataProvider')]
    public function testBeforeDeletionEvent(
        array $ids,
        string $defaultId = '',
        ?string $expectedErrorCode = null,
        string $versionId = Defaults::LIVE_VERSION
    ): void {
        $connection = $this->getFetchAllAssociativeConnection($defaultId);
        $cmsPageDefaultChangeSubscriber = new CmsPageDefaultChangeSubscriber($connection);
        $actual = null;

        try {
            $cmsPageDefaultChangeSubscriber
                ->beforeDeletion($this->getBeforeDeleteEvent($ids, $versionId));
        } catch (CmsException $exception) {
            $actual = $exception;
        }

        if (!$expectedErrorCode) {
            static::assertNull($actual);

            return;
        }

        static::assertNotNull($actual);
        static::assertSame($expectedErrorCode, $actual->getErrorCode());
    }

    /**
     * @param array<mixed> $connectionData
     */
    #[DataProvider('beforeSystemConfigChangedEventDataProvider')]
    public function testBeforeSystemConfigChangedEvent(
        BeforeSystemConfigChangedEvent $event,
        array $connectionData,
        ?string $expectedErrorCode = null
    ): void {
        $connection = $this->getConnectionMock($connectionData);
        $cmsPageDefaultChangeSubscriber = new CmsPageDefaultChangeSubscriber($connection);
        $actual = null;

        try {
            $cmsPageDefaultChangeSubscriber
                ->validateChangeOfDefaultCmsPage($event);
        } catch (CmsException|PageNotFoundException $exception) {
            $actual = $exception;
        }

        if (!$expectedErrorCode) {
            static::assertNull($actual);

            return;
        }

        static::assertNotNull($actual);
        static::assertSame($expectedErrorCode, $actual->getErrorCode());
    }

    public static function beforeDeletionEventDataProvider(): \Generator
    {
        yield 'Is does nothing if no cms page is affected' => [
            [],
        ];

        yield 'It does nothing if cms page is not default' => [
            ['cmsPageId'],
            'defaultCmsPageId',
        ];

        yield 'It throws exception before deletion of default cms page' => [
            ['defaultCmsPageId'],
            'defaultCmsPageId',
            CmsException::DELETION_OF_DEFAULT_CODE,
        ];

        yield 'It throws exception before deletion of multiple cms pages' => [
            ['cmsPage1', 'cmsPage2', 'cmsPage3', 'defaultCmsPageId'],
            'defaultCmsPageId',
            CmsException::DELETION_OF_DEFAULT_CODE,
        ];

        yield 'It does nothing if cms page is not on live version' => [
            ['cmsPageId'],
            'defaultCmsPageId',
            null,
            'not-live-version-id',
        ];
    }

    public static function beforeSystemConfigChangedEventDataProvider(): \Generator
    {
        yield 'It does nothing if no cms page default related config is changed' => [
            new BeforeSystemConfigChangedEvent('differentSystemConfigKey', 'foobar', null),
            [],
            null,
        ];

        yield 'It throws an exception on deletion of overall default' => [
            new BeforeSystemConfigChangedEvent(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, null, null),
            [
                [
                    'method' => 'fetchOne',
                    'with' => [
                        'SELECT configuration_value FROM system_config WHERE configuration_key = :configKey AND sales_channel_id is NULL;',
                        [
                            'configKey' => CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys[0],
                        ],
                    ],
                    'willReturn' => '{"_value":"cmsPageId"}',
                ],
            ],
            CmsException::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE,
        ];

        yield 'It throws an exception if an invalid cms page id is given' => [
            new BeforeSystemConfigChangedEvent(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, [], null),
            [],
            PageNotFoundException::ERROR_CODE,
        ];

        $notExistingCmsPageId = Uuid::randomHex();
        yield 'It throws an exception if the cms page does not exist' => [
            new BeforeSystemConfigChangedEvent(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $notExistingCmsPageId, null),
            [
                [
                    'method' => 'fetchOne',
                    'with' => [
                        'SELECT count(*) FROM cms_page WHERE id = :cmsPageId AND version_id = :versionId LIMIT 1;',
                        [
                            'cmsPageId' => Uuid::fromHexToBytes($notExistingCmsPageId),
                            'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                        ],
                    ],
                    'willReturn' => '0',
                ],
            ],
            PageNotFoundException::ERROR_CODE,
        ];
    }

    /**
     * @param array<string> $cmsPageIds
     */
    private function getBeforeDeleteEvent(array $cmsPageIds, string $versionId): EntityDeleteEvent
    {
        $event = $this->createMock(EntityDeleteEvent::class);
        $event
            ->method('getIds')
            ->with(CmsPageDefinition::ENTITY_NAME)
            ->willReturn($cmsPageIds);

        $context = new Context(
            source: new SystemSource(),
            versionId: $versionId,
        );

        $event->method('getContext')->willReturn($context);

        return $event;
    }

    /**
     * @param array<array{method: string, with: array<mixed>, willReturn: mixed}> $configurations
     */
    private function getConnectionMock(array $configurations = []): Connection
    {
        $connection = $this->createMock(Connection::class);

        foreach ($configurations as $config) {
            $connection
                ->method($config['method'])
                ->with(...$config['with'])
                ->willReturn($config['willReturn']);
        }

        return $connection;
    }

    private function getFetchAllAssociativeConnection(string $id): Connection
    {
        $config[]['configuration_value'] = json_encode(['_value' => $id], \JSON_THROW_ON_ERROR);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->with(
                'SELECT DISTINCT configuration_value FROM system_config WHERE configuration_key IN (:configKeys);',
                [
                    'configKeys' => CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys,
                ],
                [
                    'configKeys' => ArrayParameterType::STRING,
                ]
            )
            ->willReturn($config);

        return $connection;
    }
}
