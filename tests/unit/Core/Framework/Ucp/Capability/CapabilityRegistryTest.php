<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\AbstractUcpCapability;
use Shopware\Core\Framework\Ucp\Capability\CapabilityRegistry;

/**
 * @internal
 */
#[CoversClass(CapabilityRegistry::class)]
class CapabilityRegistryTest extends TestCase
{
    public function testRootAndExtensionsAreSeparated(): void
    {
        $root = $this->cap('a.b.root', null);
        $ext = $this->cap('a.b.ext', 'a.b.root');

        $registry = new CapabilityRegistry([
            'a.b.root' => $root,
            'a.b.ext' => $ext,
        ]);

        static::assertSame([$root], $registry->rootCapabilities());
        static::assertSame([$ext], $registry->extensions());
        static::assertTrue($registry->has('a.b.root'));
        static::assertSame($ext, $registry->get('a.b.ext'));
        static::assertSame(['a.b.root', 'a.b.ext'], $registry->names());
    }

    public function testGetReturnsNullForUnknown(): void
    {
        static::assertNull((new CapabilityRegistry())->get('not.registered'));
    }

    /**
     * @param string|list<string>|null $extends
     */
    private function cap(string $name, string|array|null $extends): AbstractUcpCapability
    {
        return new class($name, $extends) extends AbstractUcpCapability {
            /**
             * @param string|list<string>|null $e
             */
            public function __construct(private readonly string $n, private readonly string|array|null $e)
            {
            }

            public function getName(): string
            {
                return $this->n;
            }

            public function getSpecUrl(): string
            {
                return 'https://x/spec/' . $this->n;
            }

            public function getSchemaUrl(): string
            {
                return 'https://x/schema/' . $this->n;
            }

            public function getExtends(): string|array|null
            {
                return $this->e;
            }
        };
    }
}
