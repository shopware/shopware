<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Profile\PlatformProfileValidator;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @internal
 */
#[CoversClass(PlatformProfileValidator::class)]
class PlatformProfileValidatorTest extends TestCase
{
    public function testValidProfilePasses(): void
    {
        $validator = new PlatformProfileValidator();
        $validator->validate('https://agent.example/profile', [
            'ucp' => [
                'version' => '2026-01-23',
                'capabilities' => [
                    'dev.ucp.shopping.cart' => [
                        ['version' => '2026-01-23', 'spec' => 'https://ucp.dev/2026-01-23/specification/cart'],
                    ],
                ],
            ],
            'signing_keys' => [],
        ]);
        $this->expectNotToPerformAssertions();
    }

    public function testMissingUcpObjectRejected(): void
    {
        $this->expectException(UcpException::class);
        (new PlatformProfileValidator())->validate('https://x/profile', ['signing_keys' => []]);
    }

    public function testInvalidVersionRejected(): void
    {
        $this->expectException(UcpException::class);
        (new PlatformProfileValidator())->validate('https://x/profile', ['ucp' => ['version' => 'draft']]);
    }

    public function testNamespaceMismatchRejected(): void
    {
        $this->expectException(UcpException::class);
        (new PlatformProfileValidator())->validate('https://x/profile', [
            'ucp' => [
                'version' => '2026-01-23',
                'capabilities' => [
                    'dev.ucp.shopping.cart' => [
                        // namespace is dev.ucp but spec lives on example.com -> mismatch
                        ['version' => '2026-01-23', 'spec' => 'https://example.com/spec/cart'],
                    ],
                ],
            ],
        ]);
    }

    public function testVendorNamespaceMatchesItsOwnDomain(): void
    {
        $validator = new PlatformProfileValidator();
        $validator->validate('https://x/profile', [
            'ucp' => [
                'version' => '2026-01-23',
                'capabilities' => [
                    'com.example.payments.installments' => [
                        ['version' => '2026-01-23', 'spec' => 'https://example.com/specs/installments'],
                    ],
                ],
            ],
        ]);
        $this->expectNotToPerformAssertions();
    }
}
