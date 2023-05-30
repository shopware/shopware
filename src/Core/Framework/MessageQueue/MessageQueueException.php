<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class MessageQueueException extends HttpException
{
    public const NO_VALID_RECEIVER_NAME_PROVIDED = 'FRAMEWORK__NO_VALID_RECEIVER_NAME_PROVIDED';

    public static function validReceiverNameNotProvided(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NO_VALID_RECEIVER_NAME_PROVIDED,
            'No receiver name provided.',
        );
    }
}
