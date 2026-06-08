<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Upload\PresignedUploadFinalizePayload;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PresignedUploadFinalizePayload::class)]
class PresignedUploadFinalizePayloadTest extends TestCase
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
        $payload = new PresignedUploadFinalizePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: 'media/ab/cd/test-file.jpg',
        );

        $violations = $this->validator->validate($payload);

        static::assertCount(0, $violations);
    }

    /**
     * @return iterable<string, array{PresignedUploadFinalizePayload, list<string>}>
     */
    public static function invalidDataProvider(): iterable
    {
        yield 'missing all required fields' => [
            new PresignedUploadFinalizePayload(),
            ['fileName', 'extension', 'mimeType', 'path'],
        ];

        yield 'missing fileName' => [
            new PresignedUploadFinalizePayload(extension: 'jpg', mimeType: 'image/jpeg', path: 'some/path'),
            ['fileName'],
        ];

        yield 'missing extension' => [
            new PresignedUploadFinalizePayload(fileName: 'test', mimeType: 'image/jpeg', path: 'some/path'),
            ['extension'],
        ];

        yield 'missing mimeType' => [
            new PresignedUploadFinalizePayload(fileName: 'test', extension: 'jpg', path: 'some/path'),
            ['mimeType'],
        ];

        yield 'missing path' => [
            new PresignedUploadFinalizePayload(fileName: 'test', extension: 'jpg', mimeType: 'image/jpeg'),
            ['path'],
        ];
    }

    /**
     * @param list<string> $expectedFields
     */
    #[DataProvider('invalidDataProvider')]
    public function testRejectsBlankFields(
        PresignedUploadFinalizePayload $payload,
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
        $payload = new PresignedUploadFinalizePayload(
            fileName: 'test',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: 'media/path.jpg',
            width: null,
            height: null,
        );

        $violations = $this->validator->validate($payload);

        static::assertCount(0, $violations);
    }
}
