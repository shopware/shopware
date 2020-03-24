<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

class CredentialsService
{
    private const CREDENTIAL_PATH = __DIR__ . '/../wiki.secret';

    public function credentialsFileExists(): bool
    {
        return file_exists(self::CREDENTIAL_PATH);
    }

    /**
     * @throws InvalidCredentialsFileException
     */
    public function getCredentials(): CredentialsStruct
    {
        $credentialsContents = file_get_contents(self::CREDENTIAL_PATH);
        $credentials = json_decode($credentialsContents, true);

        if (!$credentials['token'] || !$credentials['url'] || !$credentials['rootCategoryId'] || !$credentials['idMapperUrl']) {
            throw new InvalidCredentialsFileException('Invalid \'wiki.secret\' file. Token, URL, ID mapper URL or root category ID not available.');
        }

        return new CredentialsStruct($credentials['token'], $credentials['url'], (int) $credentials['rootCategoryId'], $credentials['idMapperUrl']);
    }
}
