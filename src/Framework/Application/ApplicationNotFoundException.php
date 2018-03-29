<?php

namespace Shopware\Framework\Application;

use Shopware\Framework\ShopwareException;

class ApplicationNotFoundException extends \RuntimeException implements ShopwareException
{
    protected $message = 'Request could not be matched to an application.';
}