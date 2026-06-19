<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class IllegalFileNameException extends MediaException
{
    public function __construct(string $filename, string $cause)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_ILLEGAL_FILE_NAME,
            'Provided filename "{{ fileName }}" is not permitted: {{ cause }}',
            ['fileName' => $filename, 'cause' => $cause]
        );
    }
}
