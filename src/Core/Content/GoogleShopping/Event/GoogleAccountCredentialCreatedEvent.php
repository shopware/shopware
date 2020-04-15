<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Event;

use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequest;
use Symfony\Contracts\EventDispatcher\Event;

class GoogleAccountCredentialCreatedEvent extends Event
{
    public const EVENT_NAME = 'google_shoping.after_credential_refreshed';

    /**
     * @var GoogleShoppingRequest
     */
    private $request;

    /**
     * @var GoogleAccountCredential
     */
    private $accountCredential;

    public function __construct(GoogleAccountCredential $accountCredential, GoogleShoppingRequest $request)
    {
        $this->request = $request;
        $this->accountCredential = $accountCredential;
    }

    public function getGoogleAccountCredential(): GoogleAccountCredential
    {
        return $this->accountCredential;
    }

    public function getGoogleShoppingRequest(): GoogleShoppingRequest
    {
        return $this->request;
    }
}
