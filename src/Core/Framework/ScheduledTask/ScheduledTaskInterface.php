<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ScheduledTask;

interface ScheduledTaskInterface
{
    public function getTaskId(): string;

    public function setTaskId(string $taskId): void;

    public static function getTaskName(): string;

    /**
     * @return int the default interval this task should run in seconds
     */
    public static function getDefaultInterval(): int;
}
