<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log\Monolog;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Log\PackageService;

/**
 * @internal
 */
#[Package('framework')]
class AnnotatePackageProcessor implements ProcessorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PackageService $packageService
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(LogRecord $record)
    {
        $packages = [];

        $exception = $record->context['exception'] ?? null;
        if ($exception instanceof \ErrorException && str_starts_with($exception->getMessage(), 'User Deprecated:')) {
            return $record;
        }

        $packages = $this->packageService->getPackageTrace($exception);

        if ($packages !== []) {
            $record->extra[Package::PACKAGE_TRACE_ATTRIBUTE_KEY] = $packages;
        }

        return $record;
    }
}
