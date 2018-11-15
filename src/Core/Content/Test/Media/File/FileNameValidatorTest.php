<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\File\FileNameValidator;

class FileNameValidatorTest extends TestCase
{
    public function restrictedCharacters()
    {
        return array_map(function ($value) {
            return [$value];
        },
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
                '@',
            ]
        );
    }

    public function ntfsInternals()
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

    public function controlCharacters()
    {
        $c = [];

        foreach (range(0, 31) as $value) {
            $c[] = [chr($value)];
        }

        return $c;
    }

    public function test_validateFileName_throwsExceptionIfFileNameIsEmpty()
    {
        self::expectException(EmptyMediaFilenameException::class);
        $validator = new FileNameValidator();
        $validator->validateFileName('');
    }

    public function test_validateFileName_throwsIfFileNameIsOnlyDots()
    {
        self::expectException(IllegalFileNameException::class);
        self::expectExceptionMessage('Filename must not start with a "." (dot).');

        $validator = new FileNameValidator();
        $validator->validateFileName('..');
    }

    public function test_validateFileName_ThrowsIfFileNameStartsWithDot()
    {
        self::expectException(IllegalFileNameException::class);
        self::expectExceptionMessage('Filename must not start with a "." (dot).');

        $validator = new FileNameValidator();
        $validator->validateFileName('.hidden file');
    }

    public function test_validateFileName_ThrowsIfFileNameEndsWithDot()
    {
        self::expectException(IllegalFileNameException::class);
        self::expectExceptionMessage('Filename must not end with a "." (dot).');

        $validator = new FileNameValidator();
        $validator->validateFileName('file without extension.');
    }

    /**
     * @dataProvider restrictedCharacters
     */
    public function test_validateFileName_throwsIfRestrictedCharacterIsPresent($input)
    {
        self::expectException(IllegalFileNameException::class);
        self::expectExceptionMessage("Filename must not contain \"$input\"");

        $validator = new FileNameValidator();
        $validator->validateFileName($input);
    }

    /**
     * @dataProvider ntfsInternals
     */
    public function test_validateFileName_throwsIfFileNameIsNtfsInternal($input)
    {
        self::expectException(IllegalFileNameException::class);
        self::expectExceptionMessage('Filename must not contain "$"');

        $validator = new FileNameValidator();
        $validator->validateFileName($input);
    }

    /**
     * @dataProvider controlCharacters
     */
    public function test_validateFileName_throwsExceptionIfControlCharacterIsPresent($input)
    {
        self::expectException(IllegalFileNameException::class);
        self::expectExceptionMessage(
            sprintf(
                'Filename must not contain character "%x"',
                ord($input)
            )
        );

        $validator = new FileNameValidator();
        $validator->validateFileName($input);
    }

    public function test_validateFileName_throwsExceptionIfFileNameEndsWithSpaces()
    {
        self::expectException(IllegalFileNameException::class);
        self::expectExceptionMessage('Filename must not end with spaces');

        $validator = new FileNameValidator();
        $validator->validateFileName('file ');
    }
}
