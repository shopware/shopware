<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\CorruptedPathDetected;
use League\Flysystem\WhitespacePathNormalizer;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class FileNameValidator
{
    private const RESTRICTED_CHARACTERS = [
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
    ];

    private const MAX_FILE_NAME_LENGTH = 255;

    private readonly WhitespacePathNormalizer $whitespacePathNormalizer;

    public function __construct()
    {
        $this->whitespacePathNormalizer = new WhitespacePathNormalizer();
    }

    /**
     * @throws MediaException
     */
    public function validateFileName(string $fileName): void
    {
        if (empty($fileName)) {
            throw MediaException::emptyMediaFilename();
        }

        $this->validateFileNameDoesNotEndWithSpaces($fileName);
        $this->validateFileNameDoesNotEndOrStartWithDot($fileName);
        $this->validateFileNameDoesNotContainForbiddenCharacter($fileName);
        $this->validateFileNameDoesNotContainC0Character($fileName);
        $this->validateFileNameIsLessOrEqualThanMaxLength($fileName);
        $this->validateFileNameDoesNotContainFunkyWhiteSpace($fileName);
    }

    private function validateFileNameDoesNotContainFunkyWhiteSpace(string $fileName): void
    {
        try {
            $this->whitespacePathNormalizer->normalizePath($fileName);
        } catch (CorruptedPathDetected) {
            throw MediaException::illegalFileName($fileName, 'Filename must not contain funky whitespace');
        }
    }

    private function validateFileNameDoesNotEndOrStartWithDot(string $fileName): void
    {
        if (mb_substr($fileName, 0, 1) === '.') {
            throw MediaException::illegalFileName($fileName, 'Filename must not start with a "." (dot).');
        }

        if (mb_substr($fileName, mb_strlen($fileName) - 1) === '.') {
            throw MediaException::illegalFileName($fileName, 'Filename must not end with a "." (dot).');
        }
    }

    private function validateFileNameDoesNotContainForbiddenCharacter(string $fileName): void
    {
        foreach (self::RESTRICTED_CHARACTERS as $character) {
            if (mb_strpos($fileName, $character) !== false) {
                throw MediaException::illegalFileName($fileName, sprintf(
                    'Filename must not contain "%s"',
                    $character
                ));
            }
        }
    }

    private function validateFileNameDoesNotContainC0Character(string $fileName): void
    {
        foreach (range(0, 31) as $controlCharacter) {
            if (mb_strpos($fileName, \chr($controlCharacter)) !== false) {
                throw MediaException::illegalFileName($fileName, sprintf(
                    'Filename must not contain character "%x"',
                    $controlCharacter
                ));
            }
        }
    }

    private function validateFileNameDoesNotEndWithSpaces(string $fileName): void
    {
        if (mb_substr($fileName, -1) === ' ') {
            throw MediaException::illegalFileName($fileName, 'Filename must not end with spaces');
        }
    }

    private function validateFileNameIsLessOrEqualThanMaxLength(string $fileName): void
    {
        if (\strlen($fileName) <= self::MAX_FILE_NAME_LENGTH) {
            return;
        }

        throw MediaException::fileNameTooLong(self::MAX_FILE_NAME_LENGTH);
    }
}
