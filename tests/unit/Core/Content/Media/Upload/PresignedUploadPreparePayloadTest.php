<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Upload\PresignedUploadPreparePayload;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PresignedUploadPreparePayload::class)]
class PresignedUploadPreparePayloadTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidWithAllRequiredFields(): void
    {
        $payload = new PresignedUploadPreparePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
        );

        $violations = $this->validator->validate($payload);

        static::assertCount(0, $violations);
    }

    /**
     * @return iterable<string, array{PresignedUploadPreparePayload, list<string>}>
     */
    public static function invalidDataProvider(): iterable
    {
        yield 'missing all required fields' => [
            new PresignedUploadPreparePayload(),
            ['fileName', 'extension', 'mimeType'],
        ];

        yield 'missing fileName' => [
            new PresignedUploadPreparePayload(extension: 'jpg', mimeType: 'image/jpeg'),
            ['fileName'],
        ];

        yield 'missing extension' => [
            new PresignedUploadPreparePayload(fileName: 'test', mimeType: 'image/jpeg'),
            ['extension'],
        ];

        yield 'missing mimeType' => [
            new PresignedUploadPreparePayload(fileName: 'test', extension: 'jpg'),
            ['mimeType'],
        ];
    }

    /**
     * @param list<string> $expectedFields
     */
    #[DataProvider('invalidDataProvider')]
    public function testRejectsBlankFields(
        PresignedUploadPreparePayload $payload,
        array $expectedFields,
    ): void {
        $violations = $this->validator->validate($payload);

        static::assertCount(\count($expectedFields), $violations);

        $violatedProperties = [];
        foreach ($violations as $violation) {
            $violatedProperties[] = $violation->getPropertyPath();
        }

        foreach ($expectedFields as $field) {
            static::assertContains($field, $violatedProperties);
        }
    }

    public function testOptionalFieldsAcceptNull(): void
    {
        $payload = new PresignedUploadPreparePayload(
            fileName: 'test',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            mediaFolderId: null,
            private: false,
            mediaId: null,
        );

        $violations = $this->validator->validate($payload);

        static::assertCount(0, $violations);
    }
}
