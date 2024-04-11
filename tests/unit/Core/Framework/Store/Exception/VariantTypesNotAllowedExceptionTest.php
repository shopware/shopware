<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\VariantTypesNotAllowedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(VariantTypesNotAllowedException::class)]
class VariantTypesNotAllowedExceptionTest extends TestCase
{
    private VariantTypesNotAllowedException $exception;

    protected function setUp(): void
    {
        $typeViolations = [
            [
                'variantType' => 'rent',
                'extensionName' => 'SwagPaypal',
                'extensionId' => 123,
            ],
            [
                'variantType' => 'buy',
                'extensionName' => 'SwagPlugin',
                'extensionId' => 456,
            ],
        ];

        $this->exception = new VariantTypesNotAllowedException($typeViolations);
    }

    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__NOT_ALLOWED_VARIANT_TYPE',
            $this->exception->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_FORBIDDEN,
            $this->exception->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            "The variant types of the following cart positions are not allowed:\nType \"rent\" for \"SwagPaypal\" (ID: 123)\nType \"buy\" for \"SwagPlugin\" (ID: 456)",
            $this->exception->getMessage()
        );
    }
}
