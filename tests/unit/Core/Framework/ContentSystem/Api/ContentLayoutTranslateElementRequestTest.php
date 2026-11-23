<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutTranslateElementRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The DTO's own constraint attributes, evaluated the way `#[MapRequestPayload]` evaluates them at the HTTP
 * boundary: against the class metadata, which is what an attribute-mapped validator builds here. The key and
 * value rules the operation owns need the type registry and are not reachable from this surface.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutTranslateElementRequest::class)]
class ContentLayoutTranslateElementRequestTest extends TestCase
{
    #[TestDox('accepts a request that translates one key')]
    public function testAcceptsARequestCarryingOneValue(): void
    {
        $request = new ContentLayoutTranslateElementRequest(elementId: 'block-a', expectedVersion: null, values: ['headline' => ['language-en' => 'Hi']]);

        $violations = $this->validator()->validate($request);

        static::assertCount(0, $violations);
    }

    #[TestDox('rejects a request that translates nothing, on the values property')]
    public function testRejectsARequestWithEmptyValues(): void
    {
        $violations = $this->validator()->validate(new ContentLayoutTranslateElementRequest(elementId: 'block-a', expectedVersion: null));

        static::assertCount(1, $violations);
        static::assertSame('values', $violations->get(0)->getPropertyPath());
        static::assertSame(Count::TOO_FEW_ERROR, $violations->get(0)->getCode());
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
