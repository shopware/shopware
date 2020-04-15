<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Service;

use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Content\GoogleShopping\Exception\InvalidGoogleAuthorizationCodeException;

class GoogleShoppingAuthenticator
{
    /**
     * @var GoogleShoppingClient
     */
    private $client;

    public function __construct(GoogleShoppingClient $client)
    {
        $this->client = $client;
    }

    public function authorize(string $code): GoogleAccountCredential
    {
        $credential = $this->client->fetchAccessTokenWithAuthCode($code);

        if (!array_key_exists('access_token', $credential)
            || !array_key_exists('refresh_token', $credential)
            || !array_key_exists('id_token', $credential)) {
            throw new InvalidGoogleAuthorizationCodeException();
        }

        return new GoogleAccountCredential($credential);
    }
}
