<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Framework\Log\Package;

#[Package('content')]
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

    /**
     * @throws EmptyMediaFilenameException
     * @throws IllegalFileNameException
     */
    public function validateFileName(string $fileName): void
    {
        if (empty($fileName)) {
            throw new EmptyMediaFilenameException();
        }

        $this->validateFileNameDoesNotEndWithSpaces($fileName);
        $this->validateFileNameDoesNotEndOrStartWithDot($fileName);
        $this->validateFileNameDoesNotContainForbiddenCharacter($fileName);
        $this->validateFileNameDoesNotContainC0Character($fileName);
    }

    private function validateFileNameDoesNotEndOrStartWithDot(string $fileName): void
    {
        if (mb_substr($fileName, 0, 1) === '.') {
            throw new IllegalFileNameException($fileName, 'Filename must not start with a "." (dot).');
        }

        if (mb_substr($fileName, mb_strlen($fileName) - 1) === '.') {
            throw new IllegalFileNameException($fileName, 'Filename must not end with a "." (dot).');
        }
    }

    private function validateFileNameDoesNotContainForbiddenCharacter(string $fileName): void
    {
        foreach (self::RESTRICTED_CHARACTERS as $character) {
            if (mb_strpos($fileName, $character) !== false) {
                throw new IllegalFileNameException(
                    $fileName,
                    sprintf(
                        'Filename must not contain "%s"',
                        $character
                    )
                );
            }
        }
    }

    private function validateFileNameDoesNotContainC0Character(string $fileName): void
    {
        foreach (range(0, 31) as $controlCharacter) {
            if (mb_strpos($fileName, \chr($controlCharacter)) !== false) {
                throw new IllegalFileNameException(
                    $fileName,
                    sprintf(
                        'Filename must not contain character "%x"',
                        $controlCharacter
                    )
                );
            }
        }
    }

    private function validateFileNameDoesNotEndWithSpaces(string $fileName): void
    {
        if (mb_substr($fileName, -1) === ' ') {
            throw new IllegalFileNameException($fileName, 'Filename must not end with spaces');
        }
    }
}
