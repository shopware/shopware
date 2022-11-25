<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsException;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\Subscriber\CmsPageDefaultChangeSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;

/**
 * @internal
 *
 * @package content
 * @covers \Shopware\Core\Content\Cms\Subscriber\CmsPageDefaultChangeSubscriber
 */
class CmsPageDefaultChangeSubscriberTest extends TestCase
{
    public function testHasEvents(): void
    {
        $expectedEvents = [
            BeforeSystemConfigChangedEvent::class => 'validateChangeOfDefaultCmsPage',
            BeforeDeleteEvent::class => 'beforeDeletion',
        ];

        static::assertEquals($expectedEvents, CmsPageDefaultChangeSubscriber::getSubscribedEvents());
    }

    /**
     * @dataProvider beforeDeletionEventDataProvider
     */
    public function testBeforeDeletionEvent(BeforeDeleteEvent $event, Connection $connection, ?string $expectedExceptionCode = null): void
    {
        $cmsPageDefaultChangeSubscriber = new CmsPageDefaultChangeSubscriber($connection);
        $exceptionWasThrown = false;

        try {
            $cmsPageDefaultChangeSubscriber->beforeDeletion($event);
        } catch (CmsException $exception) {
            if ($expectedExceptionCode) {
                static::assertEquals($expectedExceptionCode, $exception->getErrorCode());
                $exceptionWasThrown = true;
            }
        } finally {
            if ($expectedExceptionCode && !$exceptionWasThrown) {
                static::fail('Expected exception with code ' . $expectedExceptionCode . ' to be thrown.');
            }
        }

        // assert no exception was thrown if not expected
        static::assertTrue(true);
    }

    /**
     * @dataProvider beforeSystemConfigChangedEventDataProvider
     */
    public function testBeforeSystemConfigChangedEvent(BeforeSystemConfigChangedEvent $event, Connection $connection, ?string $expectedExceptionCode = null): void
    {
        $cmsPageDefaultChangeSubscriber = new CmsPageDefaultChangeSubscriber($connection);
        $exceptionWasThrown = false;

        try {
            $cmsPageDefaultChangeSubscriber->validateChangeOfDefaultCmsPage($event);
        } catch (CmsException|PageNotFoundException $exception) {
            if ($expectedExceptionCode) {
                static::assertEquals($expectedExceptionCode, $exception->getErrorCode());
                $exceptionWasThrown = true;
            }
        } finally {
            if ($expectedExceptionCode && !$exceptionWasThrown) {
                static::fail('Expected exception with code ' . $expectedExceptionCode . ' to be thrown.');
            }
        }

        // assert no exception was thrown if not expected
        static::assertTrue(true);
    }

    /**
     * @return array<string, array{beforeDeleteEvent: BeforeDeleteEvent, connectionMock: Connection, expectedExceptionCode: string|null}>
     */
    public function beforeDeletionEventDataProvider(): iterable
    {
        yield 'Is does nothing if no cms page is affected' => [
            'beforeDeleteEvent' => $this->getBeforeDeleteEvent(),
            'connectionMock' => $this->getConnectionMock(),
            'expectedExceptionCode' => null,
        ];

        yield 'It does nothing if cms page is not default' => [
            'beforeDeleteEvent' => $this->getBeforeDeleteEvent(['cmsPageId']),
            'connectionMock' => $this->getConnectionMock([
                [
                    'method' => 'fetchAllAssociative',
                    'with' => [
                        'SELECT DISTINCT configuration_value FROM system_config WHERE configuration_key IN (:configKeys);',
                        [
                            'configKeys' => CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys,
                        ],
                        [
                            'configKeys' => Connection::PARAM_STR_ARRAY,
                        ],
                    ],
                    'willReturn' => $this->idsToSystemConfigurationArray(['defaultCmsPageId']),
                ],
            ]),
            'expectedExceptionCode' => null,
        ];

        yield 'It throws exception before deletion of default cms page' => [
            'beforeDeleteEvent' => $this->getBeforeDeleteEvent(['defaultCmsPageId']),
            'connectionMock' => $this->getConnectionMock([
                [
                    'method' => 'fetchAllAssociative',
                    'with' => [
                        'SELECT DISTINCT configuration_value FROM system_config WHERE configuration_key IN (:configKeys);',
                        [
                            'configKeys' => CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys,
                        ],
                        [
                            'configKeys' => Connection::PARAM_STR_ARRAY,
                        ],
                    ],
                    'willReturn' => $this->idsToSystemConfigurationArray(['defaultCmsPageId']),
                ],
            ]),
            'expectedExceptionCode' => CmsException::DELETION_OF_DEFAULT_CODE,
        ];

        yield 'It throws exception before deletion of multiple cms pages' => [
            'beforeDeleteEvent' => $this->getBeforeDeleteEvent(['cmsPage1', 'cmsPage2', 'cmsPage3', 'defaultCmsPageId']),
            'connectionMock' => $this->getConnectionMock([
                [
                    'method' => 'fetchAllAssociative',
                    'with' => [
                        'SELECT DISTINCT configuration_value FROM system_config WHERE configuration_key IN (:configKeys);',
                        [
                            'configKeys' => CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys,
                        ],
                        [
                            'configKeys' => Connection::PARAM_STR_ARRAY,
                        ],
                    ],
                    'willReturn' => $this->idsToSystemConfigurationArray(['defaultCmsPageId']),
                ],
            ]),
            'expectedExceptionCode' => CmsException::DELETION_OF_DEFAULT_CODE,
        ];
    }

    /**
     * @return array<string, array{beforeSystemConfigChangedEvent: BeforeSystemConfigChangedEvent, connectionMock: Connection, expectedExceptionCode: string|null}>
     */
    public function beforeSystemConfigChangedEventDataProvider(): iterable
    {
        yield 'It does nothing if no cms page default related config is changed' => [
            'beforeSystemConfigChangedEvent' => $this->getBeforeSystemConfigChangedEvent('differentSystemConfigKey', 'foobar'),
            'connectionMock' => $this->getConnectionMock(),
            'expectedExceptionCode' => null,
        ];

        yield 'It throws an exception on deletion of overall default' => [
            'beforeSystemConfigChangedEvent' => $this->getBeforeSystemConfigChangedEvent(CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys[0], null),
            'connectionMock' => $this->getConnectionMock([
                [
                    'method' => 'fetchOne',
                    'with' => [
                        'SELECT configuration_value FROM system_config WHERE configuration_key = :configKey AND sales_channel_id is NULL;',
                        [
                            'configKey' => CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys[0],
                        ],
                    ],
                    'willReturn' => $this->idsToSystemConfigurationArray(['cmsPageId'])[0]['configuration_value'],
                ],
            ]),
            'expectedExceptionCode' => CmsException::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE,
        ];

        yield 'It throws an exception if an invalid cms page id is given' => [
            'beforeSystemConfigChangedEvent' => $this->getBeforeSystemConfigChangedEvent(CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys[0], []),
            'connectionMock' => $this->getConnectionMock(),
            'expectedExceptionCode' => PageNotFoundException::ERROR_CODE,
        ];

        $notExistingCmsPageId = Uuid::randomHex();
        yield 'It throws an exception if the cms page does not exist' => [
            'beforeSystemConfigChangedEvent' => $this->getBeforeSystemConfigChangedEvent(CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys[0], $notExistingCmsPageId),
            'connectionMock' => $this->getConnectionMock([
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
            ]),
            'expectedExceptionCode' => PageNotFoundException::ERROR_CODE,
        ];
    }

    /**
     * @param list<string> $cmsPageIds
     */
    private function getBeforeDeleteEvent(array $cmsPageIds = []): BeforeDeleteEvent
    {
        $event = $this->createMock(BeforeDeleteEvent::class);
        $event
            ->method('getIds')
            ->with(CmsPageDefinition::ENTITY_NAME)
            ->willReturn($cmsPageIds);

        return $event;
    }

    /**
     * @param mixed $value
     */
    private function getBeforeSystemConfigChangedEvent(string $key, $value, ?string $salesChannelId = null): BeforeSystemConfigChangedEvent
    {
        return new BeforeSystemConfigChangedEvent($key, $value, $salesChannelId);
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

    /**
     * @param list<string> $ids
     *
     * @return array<int, array{configuration_value: string}>
     */
    private function idsToSystemConfigurationArray(array $ids): array
    {
        $config = [];

        foreach ($ids as $id) {
            $config[]['configuration_value'] = json_encode(['_value' => $id], \JSON_THROW_ON_ERROR);
        }

        return $config;
    }
}
