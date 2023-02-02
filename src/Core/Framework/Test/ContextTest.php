<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class ContextTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $context = Context::createDefaultContext();

        static::assertInstanceOf(SystemSource::class, $context->getSource());
        static::assertEquals(Context::SYSTEM_SCOPE, $context->getScope());
        static::assertEquals([], $context->getRuleIds());
        static::assertEquals(Defaults::LIVE_VERSION, $context->getVersionId());
    }

    public function testScope(): void
    {
        $context = Context::createDefaultContext();

        static::assertEquals(Context::SYSTEM_SCOPE, $context->getScope());

        $context->scope('foo', function (Context $context): void {
            static::assertEquals('foo', $context->getScope());
        });

        static::assertEquals(Context::SYSTEM_SCOPE, $context->getScope());
    }

    public function testVersionChange(): void
    {
        $versionId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $versionContext = $context->createWithVersionId($versionId);

        static::assertEquals(Defaults::LIVE_VERSION, $context->getVersionId());
        static::assertEquals($versionId, $versionContext->getVersionId());
    }

    public function testVersionChangeInheritsExtensions(): void
    {
        $context = Context::createDefaultContext();
        $context->addExtension('foo', new ArrayEntity());

        static::assertNotNull($context->getExtension('foo'));

        $versionContext = $context->createWithVersionId(Uuid::randomHex());

        static::assertNotNull($versionContext->getExtension('foo'));
    }
}
