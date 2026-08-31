<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Aggregate\FlowAction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
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
}
