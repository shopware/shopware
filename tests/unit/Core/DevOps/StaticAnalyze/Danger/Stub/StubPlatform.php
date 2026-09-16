<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub;

use Danger\Config;
use Danger\Platform\AbstractPlatform;
use Danger\Struct\PullRequest;

/**
 * @internal
 */
class StubPlatform extends AbstractPlatform
{
    public function __construct(PullRequest $pullRequest)
    {
        $this->pullRequest = $pullRequest;
    }

    public function load(string $projectIdentifier, string $id): void
    {
    }

    public function post(string $body, Config $config): string
    {
        return '';
    }

    public function removePost(Config $config): void
    {
    }

    public function hasDangerMessage(): bool
    {
        return false;
    }
}
