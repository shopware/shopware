<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Upload\PresignedUploadConfirmPayload;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PresignedUploadConfirmPayload::class)]
class PresignedUploadConfirmPayloadTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    /**
     * @param list<string> $expectedViolatedFields
     */
    #[DataProvider('payloadProvider')]
    public function testValidatesPayload(string $uploadToken, ?int $width, ?int $height, array $expectedViolatedFields): void
    {
        $payload = new PresignedUploadConfirmPayload(uploadToken: $uploadToken, width: $width, height: $height);

        $violatedFields = [];
        foreach ($this->validator->validate($payload) as $violation) {
            $violatedFields[] = $violation->getPropertyPath();
        }

        static::assertSame($expectedViolatedFields, $violatedFields);
    }

    /**
     * @return iterable<string, array{string, ?int, ?int, list<string>}>
     */
    public static function payloadProvider(): iterable
    {
        yield 'token without dimensions is valid' => ['signed.token', null, null, []];
        yield 'token with dimensions is valid' => ['signed.token', 800, 600, []];
        yield 'blank upload token is rejected' => ['', null, null, ['uploadToken']];
        yield 'zero width is rejected' => ['signed.token', 0, 600, ['width']];
        yield 'negative height is rejected' => ['signed.token', 800, -1, ['height']];
    }
}
