<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\UtilException;
use Symfony\Component\HttpFoundation\Response;

#[Package('services-settings')]
class UtilXmlParsingException extends UtilException
{
    public function __construct(
        string $xmlFile,
        string $message
    ) {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::XML_PARSE_ERROR,
            'Unable to parse file "{{ file }}". Message: {{ message }}',
            ['file' => $xmlFile, 'message' => $message]
        );
    }
}
