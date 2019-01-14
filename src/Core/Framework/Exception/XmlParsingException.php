<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Throwable;

class XmlParsingException extends ShopwareHttpException
{
    protected $code = 'XML-PARSE-ERROR';

    public function __construct(string $xmlFile, string $message, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Unable to parse file "%s". Message: %s', $xmlFile, $message);

        parent::__construct($message, $code, $previous);
    }
}
