<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Event;

use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class GoogleAccountCredentialRefreshedEvent extends Event
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var GoogleAccountCredential
     */
    private $accountCredential;

    public function __construct(GoogleAccountCredential $accountCredential, Context $context)
    {
        $this->context = $context;
        $this->accountCredential = $accountCredential;
    }

    public function getContext(): context
    {
        return $this->context;
    }

    public function getGoogleAccountCredential(): GoogleAccountCredential
    {
        return $this->accountCredential;
    }
}
