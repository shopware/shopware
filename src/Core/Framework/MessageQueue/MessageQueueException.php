<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class MessageQueueException extends HttpException
{
    public const NO_VALID_RECEIVER_NAME_PROVIDED = 'FRAMEWORK__NO_VALID_RECEIVER_NAME_PROVIDED';
    public const QUEUE_CANNOT_UNSERIALIZE_MESSAGE = 'FRAMEWORK__QUEUE_CANNOT_UNSERIALIZE_MESSAGE';
    public const WORKER_IS_LOCKED = 'FRAMEWORK__WORKER_IS_LOCKED';

    public static function validReceiverNameNotProvided(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NO_VALID_RECEIVER_NAME_PROVIDED,
            'No receiver name provided.',
        );
    }

    public static function cannotUnserializeMessage(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::QUEUE_CANNOT_UNSERIALIZE_MESSAGE,
            'Cannot unserialize message {{ message }}',
            ['message' => $message]
        );
    }

    public static function workerIsLocked(string $receiver): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::WORKER_IS_LOCKED,
            'Another worker is already running for receiver: "{{ receiver }}"',
            ['receiver' => $receiver]
        );
    }
}
