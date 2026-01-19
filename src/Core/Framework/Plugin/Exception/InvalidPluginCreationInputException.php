<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class InvalidPluginCreationInputException extends PluginException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::PLUGIN_CREATION_INVALID_ENTRY,
            'Invalid input provided during plugin creation. Error: {{ reason }}',
            [
                'reason' => $reason,
            ]
        );
    }
}
