<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenStore;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextHandoffTokenStore::class)]
class ContextHandoffTokenStoreTest extends TestCase
{
    private ArrayAdapter $cache;

    private ContextHandoffTokenStore $store;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter(storeSerialized: false);
        $this->store = new ContextHandoffTokenStore($this->cache);
    }

    public function testStoredContextTokenIsReturnedOnce(): void
    {
        $jti = Uuid::randomHex();
        $this->store->store($jti, 'the-context-token', 60);

        static::assertSame('the-context-token', $this->store->consume($jti));
        static::assertNull($this->store->consume($jti));
    }

    public function testConsumeReturnsNullForAnUnknownJti(): void
    {
        static::assertNull($this->store->consume(Uuid::randomHex()));
    }

    public function testEntryIsStoredUnderTheJtiPrefixedKey(): void
    {
        $jti = Uuid::randomHex();
        $this->store->store($jti, 'the-context-token', 60);

        $item = $this->cache->getItem('context-handoff-' . $jti);
        static::assertTrue($item->isHit());
        static::assertSame('the-context-token', $item->get());
    }

    public function testExpiredEntryIsNotReturned(): void
    {
        $jti = Uuid::randomHex();
        $this->store->store($jti, 'the-context-token', -1);

        static::assertNull($this->store->consume($jti));
    }

    public function testEntriesOfDifferentTokensDoNotInterfere(): void
    {
        $first = Uuid::randomHex();
        $second = Uuid::randomHex();

        $this->store->store($first, 'first-context-token', 60);
        $this->store->store($second, 'second-context-token', 60);

        static::assertSame('first-context-token', $this->store->consume($first));
        static::assertSame('second-context-token', $this->store->consume($second));
    }
}
