<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Indexing\CreateAliasTask
 */
class CreateAliasTaskTest extends TestCase
{
    public function testShouldRun(): void
    {
        static::assertTrue(CreateAliasTask::shouldRun(new ParameterBag(['elasticsearch.enabled' => true])));
        static::assertFalse(CreateAliasTask::shouldRun(new ParameterBag(['elasticsearch.enabled' => false])));
    }

    public function testGetDefaultInterval(): void
    {
        static::assertSame(300, CreateAliasTask::getDefaultInterval());
    }

    public function testGetTaskName(): void
    {
        static::assertSame('shopware.elasticsearch.create.alias', CreateAliasTask::getTaskName());
    }
}
