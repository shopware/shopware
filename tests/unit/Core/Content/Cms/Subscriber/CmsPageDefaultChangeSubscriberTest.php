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
use Shopware\Core\Defaults;
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
     * @param list<string> $event
     * @param array<mixed> $connectionData
     */
    #[DataProvider('beforeDeletionEventDataProvider')]
    public function testBeforeDeletionEvent(array $event, array $connectionData, ?string $expectedExceptionCode = null): void
    {
        $connection = $this->getConnectionMock($connectionData);
        $cmsPageDefaultChangeSubscriber = new CmsPageDefaultChangeSubscriber($connection);
        $exceptionWasThrown = false;

        try {
            $cmsPageDefaultChangeSubscriber->beforeDeletion($this->getBeforeDeleteEvent($event));
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
        static::assertTrue(true); // @phpstan-ignore-line
    }

    /**
     * @param array<mixed> $connectionData
     */
    #[DataProvider('beforeSystemConfigChangedEventDataProvider')]
    public function testBeforeSystemConfigChangedEvent(BeforeSystemConfigChangedEvent $event, array $connectionData, ?string $expectedExceptionCode = null): void
    {
        $connection = $this->getConnectionMock($connectionData);
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
        static::assertTrue(true); // @phpstan-ignore-line
    }

    /**
     * @return array<string, array{list<string>, array<mixed>, string|null}>
     */
    public static function beforeDeletionEventDataProvider(): iterable
    {
        yield 'Is does nothing if no cms page is affected' => [
            [],
            [],
            null,
        ];

        yield 'It does nothing if cms page is not default' => [
            ['cmsPageId'],
            [
                [
                    'method' => 'fetchAllAssociative',
                    'with' => [
                        'SELECT DISTINCT configuration_value FROM system_config WHERE configuration_key IN (:configKeys);',
                        [
                            'configKeys' => CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys,
                        ],
                        [
                            'configKeys' => ArrayParameterType::STRING,
                        ],
                    ],
                    'willReturn' => self::idsToSystemConfigurationArray(['defaultCmsPageId']),
                ],
            ],
            null,
        ];

        yield 'It throws exception before deletion of default cms page' => [
            ['defaultCmsPageId'],
            [
                [
                    'method' => 'fetchAllAssociative',
                    'with' => [
                        'SELECT DISTINCT configuration_value FROM system_config WHERE configuration_key IN (:configKeys);',
                        [
                            'configKeys' => CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys,
                        ],
                        [
                            'configKeys' => ArrayParameterType::STRING,
                        ],
                    ],
                    'willReturn' => self::idsToSystemConfigurationArray(['defaultCmsPageId']),
                ],
            ],
            CmsException::DELETION_OF_DEFAULT_CODE,
        ];

        yield 'It throws exception before deletion of multiple cms pages' => [
            ['cmsPage1', 'cmsPage2', 'cmsPage3', 'defaultCmsPageId'],
            [
                [
                    'method' => 'fetchAllAssociative',
                    'with' => [
                        'SELECT DISTINCT configuration_value FROM system_config WHERE configuration_key IN (:configKeys);',
                        [
                            'configKeys' => CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys,
                        ],
                        [
                            'configKeys' => ArrayParameterType::STRING,
                        ],
                    ],
                    'willReturn' => self::idsToSystemConfigurationArray(['defaultCmsPageId']),
                ],
            ],
            CmsException::DELETION_OF_DEFAULT_CODE,
        ];
    }

    /**
     * @return array<string, array{BeforeSystemConfigChangedEvent, array<mixed>, string|null}>
     */
    public static function beforeSystemConfigChangedEventDataProvider(): iterable
    {
        yield 'It does nothing if no cms page default related config is changed' => [
            self::getBeforeSystemConfigChangedEvent('differentSystemConfigKey', 'foobar'),
            [],
            null,
        ];

        yield 'It throws an exception on deletion of overall default' => [
            self::getBeforeSystemConfigChangedEvent(CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys[0], null),
            [
                [
                    'method' => 'fetchOne',
                    'with' => [
                        'SELECT configuration_value FROM system_config WHERE configuration_key = :configKey AND sales_channel_id is NULL;',
                        [
                            'configKey' => CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys[0],
                        ],
                    ],
                    'willReturn' => self::idsToSystemConfigurationArray(['cmsPageId'])[0]['configuration_value'],
                ],
            ],
            CmsException::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE,
        ];

        yield 'It throws an exception if an invalid cms page id is given' => [
            self::getBeforeSystemConfigChangedEvent(CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys[0], []),
            [],
            PageNotFoundException::ERROR_CODE,
        ];

        $notExistingCmsPageId = Uuid::randomHex();
        yield 'It throws an exception if the cms page does not exist' => [
            self::getBeforeSystemConfigChangedEvent(CmsPageDefaultChangeSubscriber::$defaultCmsPageConfigKeys[0], $notExistingCmsPageId),
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
     * @param list<string> $cmsPageIds
     */
    private function getBeforeDeleteEvent(array $cmsPageIds = []): EntityDeleteEvent
    {
        $event = $this->createMock(EntityDeleteEvent::class);
        $event
            ->method('getIds')
            ->with(CmsPageDefinition::ENTITY_NAME)
            ->willReturn($cmsPageIds);

        return $event;
    }

    private static function getBeforeSystemConfigChangedEvent(string $key, mixed $value): BeforeSystemConfigChangedEvent
    {
        return new BeforeSystemConfigChangedEvent($key, $value, null);
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
    private static function idsToSystemConfigurationArray(array $ids): array
    {
        $config = [];

        foreach ($ids as $id) {
            $config[]['configuration_value'] = json_encode(['_value' => $id], \JSON_THROW_ON_ERROR);
        }

        return $config;
    }
}
