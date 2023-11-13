<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileNameValidator;
use Shopware\Core\Content\Media\MediaException;

/**
 * @internal
 */
class FileNameValidatorTest extends TestCase
{
    /**
     * @return array<array<string>>
     */
    public static function restrictedCharacters(): array
    {
        return array_map(
            fn ($value) => [$value],
            [
                '\\',
                '/',
                '?',
                '*',
                '%',
                '&',
                ':',
                '|',
                '"',
                '\'',
                '<',
                '>',
                '$',
                '#',
                '{',
                '}',
            ]
        );
    }

    /**
     * @return array<array<string>>
     */
    public static function ntfsInternals(): array
    {
        return [
            ['$Mft'],
            ['$MftMirr'],
            ['$LogFile'],
            ['$Volume'],
            ['$AttrDef'],
            ['$Bitmap'],
            ['$Boot'],
            ['$BadClus'],
            ['$Secure'],
            ['$Upcase'],
            ['$Extend'],
            ['$Quota'],
            ['$ObjId'],
            ['$Reparse'],
        ];
    }

    /**
     * @return array<array<string>>
     */
    public static function controlCharacters(): array
    {
        $c = [];

        foreach (range(0, 31) as $value) {
            $c[] = [\chr($value)];
        }

        return $c;
    }

    public function testValidateFileNameThrowsExceptionIfFileNameIsEmpty(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('A valid filename must be provided.');

        $validator = new FileNameValidator();
        $validator->validateFileName('');
    }

    public function testValidateFileNameThrowsIfFileNameIsOnlyDots(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Filename must not start with a "." (dot).');

        $validator = new FileNameValidator();
        $validator->validateFileName('..');
    }

    public function testValidateFileNameThrowsIfFileNameStartsWithDot(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Filename must not start with a "." (dot).');

        $validator = new FileNameValidator();
        $validator->validateFileName('.hidden file');
    }

    public function testValidateFileNameThrowsIfFileNameEndsWithDot(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Filename must not end with a "." (dot).');

        $validator = new FileNameValidator();
        $validator->validateFileName('file without extension.');
    }

    /**
     * @dataProvider restrictedCharacters
     */
    public function testValidateFileNameThrowsIfRestrictedCharacterIsPresent(string $input): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage("Filename must not contain \"$input\"");

        $validator = new FileNameValidator();
        $validator->validateFileName($input);
    }

    /**
     * @dataProvider ntfsInternals
     */
    public function testValidateFileNameThrowsIfFileNameIsNtfsInternal(string $input): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Filename must not contain "$"');

        $validator = new FileNameValidator();
        $validator->validateFileName($input);
    }

    /**
     * @dataProvider controlCharacters
     */
    public function testValidateFileNameThrowsExceptionIfControlCharacterIsPresent(string $input): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Filename must not contain character "%x"',
                \ord($input)
            )
        );

        $validator = new FileNameValidator();
        $validator->validateFileName($input);
    }

    public function testValidateFileNameThrowsExceptionIfFileNameEndsWithSpaces(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Filename must not end with spaces');

        $validator = new FileNameValidator();
        $validator->validateFileName('file ');
    }
}
