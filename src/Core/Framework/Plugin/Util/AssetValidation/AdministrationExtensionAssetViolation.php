<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util\AssetValidation;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class AdministrationExtensionAssetViolation
{
    public function __construct(
        public string $bundleName,
        public string $technicalBundleName,
        public string $assetType,
        public string $assetUrl,
        public ?string $expectedFilePath,
        public string $entrypointsFilePath,
        public string $reason,
    ) {
    }

    public function isMissingAsset(): bool
    {
        return str_contains($this->reason, 'is missing');
    }

    /**
     * @return array{
     *     bundleName: string,
     *     technicalBundleName: string,
     *     assetType: string,
     *     assetUrl: string,
     *     expectedFilePath: ?string,
     *     entrypointsFilePath: string,
     *     reason: string
     * }
     */
    public function toLogContext(): array
    {
        return [
            'bundleName' => $this->bundleName,
            'technicalBundleName' => $this->technicalBundleName,
            'assetType' => $this->assetType,
            'assetUrl' => $this->assetUrl,
            'expectedFilePath' => $this->expectedFilePath,
            'entrypointsFilePath' => $this->entrypointsFilePath,
            'reason' => $this->reason,
        ];
    }

    public function toConsoleMessage(): string
    {
        $subject = $this->assetUrl !== '' ? $this->assetUrl : $this->entrypointsFilePath;

        if ($this->expectedFilePath === null) {
            return \sprintf(
                '%s Bundle: %s, type: %s, asset: %s, entrypoints: %s',
                $this->reason,
                $this->bundleName,
                $this->assetType,
                $subject,
                $this->entrypointsFilePath
            );
        }

        return \sprintf(
            '%s Bundle: %s, type: %s, asset: %s, expected file: %s, entrypoints: %s',
            $this->reason,
            $this->bundleName,
            $this->assetType,
            $subject,
            $this->expectedFilePath,
            $this->entrypointsFilePath
        );
    }
}
