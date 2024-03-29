<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\Datadog;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class DatadogPayload
{
    public function __construct(
        private readonly string $source,
        private readonly string $tags,
        private readonly string $message,
        private readonly string $service,
        private readonly ?string $testDescription = null,
        private readonly ?float $testDuration = null
    ) {
    }

    /**
     * @return array<string, string|float|null>
     */
    public function serialize(): array
    {
        return [
            'ddsource' => $this->source,
            'ddtags' => $this->tags,
            'message' => $this->message,
            'service' => $this->service,
            'test-description' => $this->testDescription,
            'test-duration' => $this->testDuration,
        ];
    }
}
