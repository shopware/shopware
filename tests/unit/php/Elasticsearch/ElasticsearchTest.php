<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch;

use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Elasticsearch;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Elasticsearch
 */
class ElasticsearchTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $elasticsearch = new Elasticsearch();

        static::assertEquals(-1, $elasticsearch->getTemplatePriority());
    }
}
