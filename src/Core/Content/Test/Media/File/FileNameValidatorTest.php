<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\File\FileNameValidator;

/**
 * @internal
 */
class FileNameValidatorTest extends TestCase
{
    public static function restrictedCharacters()
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
        $this->expectException(EmptyMediaFilenameException::class);
        $validator = new FileNameValidator();
        $validator->validateFileName('');
    }

    public function testValidateFileNameThrowsIfFileNameIsOnlyDots(): void
    {
        $this->expectException(IllegalFileNameException::class);
        $this->expectExceptionMessage('Filename must not start with a "." (dot).');

        $validator = new FileNameValidator();
        $validator->validateFileName('..');
    }

    public function testValidateFileNameThrowsIfFileNameStartsWithDot(): void
    {
        $this->expectException(IllegalFileNameException::class);
        $this->expectExceptionMessage('Filename must not start with a "." (dot).');

        $validator = new FileNameValidator();
        $validator->validateFileName('.hidden file');
    }

    public function testValidateFileNameThrowsIfFileNameEndsWithDot(): void
    {
        $this->expectException(IllegalFileNameException::class);
        $this->expectExceptionMessage('Filename must not end with a "." (dot).');

        $validator = new FileNameValidator();
        $validator->validateFileName('file without extension.');
    }

    /**
     * @dataProvider restrictedCharacters
     */
    public function testValidateFileNameThrowsIfRestrictedCharacterIsPresent($input): void
    {
        $this->expectException(IllegalFileNameException::class);
        $this->expectExceptionMessage("Filename must not contain \"$input\"");

        $validator = new FileNameValidator();
        $validator->validateFileName($input);
    }

    /**
     * @dataProvider ntfsInternals
     */
    public function testValidateFileNameThrowsIfFileNameIsNtfsInternal($input): void
    {
        $this->expectException(IllegalFileNameException::class);
        $this->expectExceptionMessage('Filename must not contain "$"');

        $validator = new FileNameValidator();
        $validator->validateFileName($input);
    }

    /**
     * @dataProvider controlCharacters
     */
    public function testValidateFileNameThrowsExceptionIfControlCharacterIsPresent($input): void
    {
        $this->expectException(IllegalFileNameException::class);
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
        $this->expectException(IllegalFileNameException::class);
        $this->expectExceptionMessage('Filename must not end with spaces');

        $validator = new FileNameValidator();
        $validator->validateFileName('file ');
    }
}
