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
     * @return iterable<string, array{PresignedUploadConfirmPayload}>
     */
    public static function validDataProvider(): iterable
    {
        yield 'token without dimensions' => [
            new PresignedUploadConfirmPayload(uploadToken: 'signed.token'),
        ];

        yield 'token with dimensions' => [
            new PresignedUploadConfirmPayload(uploadToken: 'signed.token', width: 800, height: 600),
        ];
    }

    #[DataProvider('validDataProvider')]
    public function testAcceptsValidPayload(PresignedUploadConfirmPayload $payload): void
    {
        static::assertCount(0, $this->validator->validate($payload));
    }

    /**
     * @return iterable<string, array{PresignedUploadConfirmPayload, list<string>}>
     */
    public static function invalidDataProvider(): iterable
    {
        yield 'blank upload token' => [
            new PresignedUploadConfirmPayload(),
            ['uploadToken'],
        ];

        yield 'zero width' => [
            new PresignedUploadConfirmPayload(uploadToken: 'signed.token', width: 0, height: 600),
            ['width'],
        ];

        yield 'negative height' => [
            new PresignedUploadConfirmPayload(uploadToken: 'signed.token', width: 800, height: -1),
            ['height'],
        ];
    }

    /**
     * @param list<string> $expectedFields
     */
    #[DataProvider('invalidDataProvider')]
    public function testRejectsInvalidFields(PresignedUploadConfirmPayload $payload, array $expectedFields): void
    {
        $violations = $this->validator->validate($payload);

        $violatedProperties = [];
        foreach ($violations as $violation) {
            $violatedProperties[] = $violation->getPropertyPath();
        }

        static::assertSame($expectedFields, $violatedProperties);
    }
}
