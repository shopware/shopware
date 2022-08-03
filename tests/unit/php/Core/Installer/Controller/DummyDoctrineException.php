<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use Doctrine\DBAL\Exception\DriverException;

/**
 * @internal
 */
class DummyDoctrineException extends DriverException
{
    private int $errorCode;

    public function __construct(int $errorCode, string $message = '')
    {
        $this->errorCode = $errorCode;
        $this->message = $message;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}
