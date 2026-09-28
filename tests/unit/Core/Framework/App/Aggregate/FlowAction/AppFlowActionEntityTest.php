<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Aggregate\FlowAction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Shopware\Core\Framework\App\Aggregate\FlowActionTranslation\AppFlowActionTranslationCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppFlowActionEntity::class)]
class AppFlowActionEntityTest extends TestCase
{
    public function testJsonSerializeOmitsTheRawIcon(): void
    {
        $entity = new AppFlowActionEntity();
        $entity->setUniqueIdentifier('flow-action');
        $entity->setName('action');
        $entity->setIconRaw('binary-icon');

        $data = $entity->jsonSerialize();

        static::assertArrayNotHasKey('iconRaw', $data);
        static::assertSame('action', $data['name']);
    }

    public function testAccessorsRoundTrip(): void
    {
        $entity = new AppFlowActionEntity();

        $app = new AppEntity();
        $translations = new AppFlowActionTranslationCollection();

        $entity->setAppId('app-id');
        $entity->setApp($app);
        $entity->setName('action');
        $entity->setBadge('badge');
        $entity->setLabel('label');
        $entity->setParameters(['to' => 'admin']);
        $entity->setConfig(['subject' => 'Hello']);
        $entity->setHeaders(['content-type' => 'application/json']);
        $entity->setRequirements(['orderAware']);
        $entity->setDescription('description');
        $entity->setHeadline('headline');
        $entity->setIconRaw('icon-raw');
        $entity->setIcon('icon');
        $entity->setSwIcon('sw-icon');
        $entity->setUrl('https://example.com/action');
        $entity->setDelayable(true);
        $entity->setTranslations($translations);

        static::assertSame('app-id', $entity->getAppId());
        static::assertSame($app, $entity->getApp());
        static::assertSame('action', $entity->getName());
        static::assertSame('badge', $entity->getBadge());
        static::assertSame('label', $entity->getLabel());
        static::assertSame(['to' => 'admin'], $entity->getParameters());
        static::assertSame(['subject' => 'Hello'], $entity->getConfig());
        static::assertSame(['content-type' => 'application/json'], $entity->getHeaders());
        static::assertSame(['orderAware'], $entity->getRequirements());
        static::assertSame('description', $entity->getDescription());
        static::assertSame('headline', $entity->getHeadline());
        static::assertSame('icon-raw', $entity->getIconRaw());
        static::assertSame('icon', $entity->getIcon());
        static::assertSame('sw-icon', $entity->getSwIcon());
        static::assertSame('https://example.com/action', $entity->getUrl());
        static::assertTrue($entity->getDelayable());
        static::assertSame($translations, $entity->getTranslations());
    }
}
