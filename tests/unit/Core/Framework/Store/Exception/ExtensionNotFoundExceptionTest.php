<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package merchant-services
 *
 * @internal
 *
 * @covers \Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException
 */
class ExtensionNotFoundExceptionTest extends TestCase
{
    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__EXTENSION_NOT_FOUND',
            ExtensionNotFoundException::extensionNotFoundFromId('123')->getErrorCode()
        );
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_NOT_FOUND,
            ExtensionNotFoundException::fromTechnicalName('test-app')->getStatusCode()
        );
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testGetMessageFromTechnicalName(): void
    {
        static::assertSame(
            'Could not find extension with technical name "SwagPaypal".',
            ExtensionNotFoundException::fromTechnicalName('SwagPaypal')->getMessage()
        );
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testGetMessageFromId(): void
    {
        static::assertSame(
            'Could not find extension with id "bda4cdc0a56e43a1973d9f81139e5fcc".',
            ExtensionNotFoundException::fromId('bda4cdc0a56e43a1973d9f81139e5fcc')->getMessage()
        );
    }
}
