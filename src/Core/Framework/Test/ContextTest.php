<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\Uuid;

class ContextTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $context = Context::createDefaultContext();

        static::assertEquals(SourceContext::ORIGIN_SYSTEM, $context->getSourceContext()->getOrigin());
        static::assertEquals([], $context->getRules());
        static::assertEquals(Defaults::LIVE_VERSION, $context->getVersionId());
    }

    public function testScope(): void
    {
        $context = Context::createDefaultContext();

        static::assertEquals(SourceContext::ORIGIN_SYSTEM, $context->getSourceContext()->getOrigin());

        $context->scope('foo', function (Context $context) {
            static::assertEquals('foo', $context->getSourceContext()->getOrigin());
        });

        static::assertEquals(SourceContext::ORIGIN_SYSTEM, $context->getSourceContext()->getOrigin());
    }

    public function testVersionChange(): void
    {
        $versionId = Uuid::uuid4()->getHex();

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

        $versionContext = $context->createWithVersionId(Uuid::uuid4()->getHex());

        static::assertNotNull($versionContext->getExtension('foo'));
    }
}
