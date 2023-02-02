<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Requirements\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
abstract class RequirementCheck extends Struct
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    public const STATUS_WARNING = 'warning';

    private const ALLOWED_STATUS = [self::STATUS_SUCCESS, self::STATUS_ERROR, self::STATUS_WARNING];

    private string $name;

    private string $status;

    public function __construct(string $name, string $status)
    {
        if (empty($name)) {
            throw new \RuntimeException('Empty name for RequirementCheck provided.');
        }

        if (!\in_array($status, self::ALLOWED_STATUS, true)) {
            throw new \RuntimeException(\sprintf(
                'Invalid status for RequirementCheck, got "%s", allowed values are "%s".',
                $status,
                implode('", "', self::ALLOWED_STATUS)
            ));
        }

        $this->name = $name;
        $this->status = $status;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
