<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\CustomEntityException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class CustomEntityXmlParsingException extends CustomEntityException
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
