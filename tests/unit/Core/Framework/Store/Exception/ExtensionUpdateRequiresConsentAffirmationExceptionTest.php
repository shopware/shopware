<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package merchant-services
 *
 * @internal
 *
 * @covers \Shopware\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException
 */
class ExtensionUpdateRequiresConsentAffirmationExceptionTest extends TestCase
{
    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION',
            ExtensionUpdateRequiresConsentAffirmationException::fromDelta('SwagApp', [])->getErrorCode()
        );
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ExtensionUpdateRequiresConsentAffirmationException::fromDelta('SwagApp', [])->getStatusCode()
        );
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testGetMessage(): void
    {
        static::assertSame(
            'Updating app "SwagApp" requires a renewed consent affirmation.',
            ExtensionUpdateRequiresConsentAffirmationException::fromDelta('SwagApp', [])->getMessage()
        );
    }
}
