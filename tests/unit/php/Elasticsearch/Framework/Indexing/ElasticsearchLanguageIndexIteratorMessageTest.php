<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchLanguageIndexIteratorMessage;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Indexing\ElasticsearchLanguageIndexIteratorMessage
 */
class ElasticsearchLanguageIndexIteratorMessageTest extends TestCase
{
    public function testMessage(): void
    {
        $msg = new ElasticsearchLanguageIndexIteratorMessage('1');

        static::assertSame('1', $msg->getLanguageId());
    }
}
