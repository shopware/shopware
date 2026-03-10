<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class PluginExtractionException extends PluginException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PLUGIN_EXTRACTION_FAILED,
            'Plugin extraction failed. Error: {{ error }}',
            ['error' => $reason]
        );
    }
}
