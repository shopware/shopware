<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util\AssetValidation;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
final readonly class AdministrationExtensionAssetValidator
{
    public const ADMINISTRATION_PUBLIC_PATH = 'Resources/public/administration';

    public const ENTRYPOINTS_FILE_PATH = self::ADMINISTRATION_PUBLIC_PATH . '/.vite/entrypoints.json';

    private const REASON_MISSING = 'The referenced Administration extension asset is missing.';

    private const REASON_INVALID_PATH = 'The referenced Administration extension asset path is invalid.';

    private const REASON_MALFORMED_ASSET_LIST = 'The Administration extension asset list is malformed.';

    private const REASON_MALFORMED_ENTRYPOINTS = 'The Administration extension entrypoints file is malformed.';

    /**
     * @var list<string>
     */
    private const ASSET_TYPES = ['css', 'js', 'dynamic', 'preload'];

    public function __construct(private Filesystem $filesystem)
    {
    }

    public function getTechnicalBundleName(Bundle $bundle): string
    {
        return str_replace('_', '-', $bundle->getContainerPrefix());
    }

    public function getAssetBundleName(Bundle $bundle): string
    {
        return preg_replace('/bundle$/', '', mb_strtolower($bundle->getName())) ?? mb_strtolower($bundle->getName());
    }

    public function getEntrypointsFilePath(Bundle $bundle): string
    {
        return Path::join($bundle->getPath(), self::ENTRYPOINTS_FILE_PATH);
    }

    /**
     * @return list<AdministrationExtensionAssetViolation>
     */
    public function validateEntrypointsFile(Bundle $bundle): array
    {
        $entrypointsFilePath = $this->getEntrypointsFilePath($bundle);

        if (!$this->filesystem->exists($entrypointsFilePath) || !is_file($entrypointsFilePath)) {
            return [];
        }

        try {
            $entrypointsData = json_decode(
                $this->filesystem->readFile($entrypointsFilePath),
                true,
                flags: \JSON_THROW_ON_ERROR
            );
        } catch (\Throwable $exception) {
            return [
                $this->createViolation(
                    $bundle,
                    'entrypoints',
                    $entrypointsFilePath,
                    null,
                    self::REASON_MALFORMED_ENTRYPOINTS . ' ' . $exception->getMessage(),
                ),
            ];
        }

        if (!\is_array($entrypointsData)) {
            return [
                $this->createViolation(
                    $bundle,
                    'entrypoints',
                    $entrypointsFilePath,
                    null,
                    self::REASON_MALFORMED_ENTRYPOINTS,
                ),
            ];
        }

        return $this->validateEntrypointsData($bundle, $entrypointsData);
    }

    /**
     * @param array<string, mixed> $entrypointsData
     *
     * @return list<AdministrationExtensionAssetViolation>
     */
    public function validateEntrypointsData(Bundle $bundle, array $entrypointsData): array
    {
        $entryPoints = $entrypointsData['entryPoints'] ?? [];

        if (!\is_array($entryPoints)) {
            return [
                $this->createViolation($bundle, 'entrypoints', '', null, self::REASON_MALFORMED_ENTRYPOINTS),
            ];
        }

        $technicalBundleName = $this->getTechnicalBundleName($bundle);
        $bundleEntryPoint = $entryPoints[$technicalBundleName] ?? [];

        if ($bundleEntryPoint === []) {
            return [];
        }

        if (!\is_array($bundleEntryPoint)) {
            return [
                $this->createViolation($bundle, 'entrypoints', '', null, self::REASON_MALFORMED_ENTRYPOINTS),
            ];
        }

        $violations = [];

        foreach (self::ASSET_TYPES as $assetType) {
            $filterResult = $this->filterAssetUrls($bundle, $bundleEntryPoint[$assetType] ?? [], $assetType);
            array_push($violations, ...$filterResult->violations);
        }

        return $violations;
    }

    public function filterAssetUrls(Bundle $bundle, mixed $assetUrls, string $assetType): AdministrationExtensionAssetFilterResult
    {
        if ($assetUrls === null || $assetUrls === []) {
            return new AdministrationExtensionAssetFilterResult([], []);
        }

        if (!\is_array($assetUrls)) {
            return new AdministrationExtensionAssetFilterResult([], [
                $this->createViolation($bundle, $assetType, '', null, self::REASON_MALFORMED_ASSET_LIST),
            ]);
        }

        $filteredAssetUrls = [];
        $violations = [];

        foreach ($assetUrls as $assetUrl) {
            if (!\is_string($assetUrl) || $assetUrl === '') {
                $violations[] = $this->createViolation($bundle, $assetType, '', null, self::REASON_MALFORMED_ASSET_LIST);

                continue;
            }

            $violation = $this->validateAssetUrl($bundle, $assetType, $assetUrl);
            if ($violation !== null) {
                $violations[] = $violation;

                continue;
            }

            $filteredAssetUrls[] = $assetUrl;
        }

        return new AdministrationExtensionAssetFilterResult($filteredAssetUrls, $violations);
    }

    private function validateAssetUrl(Bundle $bundle, string $assetType, string $assetUrl): ?AdministrationExtensionAssetViolation
    {
        $path = \parse_url($assetUrl, \PHP_URL_PATH);
        if (!\is_string($path) || $path === '') {
            return null;
        }

        $decodedPath = rawurldecode($path);
        $expectedBasePath = \sprintf('/bundles/%s/administration/', $this->getAssetBundleName($bundle));
        $expectedBasePathPosition = mb_strpos($decodedPath, $expectedBasePath);

        if ($expectedBasePathPosition === false) {
            return null;
        }

        if (str_contains($decodedPath, "\0")) {
            return $this->createViolation($bundle, $assetType, $assetUrl, null, self::REASON_INVALID_PATH);
        }

        $relativeAssetPath = mb_substr($decodedPath, $expectedBasePathPosition + mb_strlen($expectedBasePath));
        if ($relativeAssetPath === '' || str_contains($relativeAssetPath, '\\')) {
            return $this->createViolation($bundle, $assetType, $assetUrl, null, self::REASON_INVALID_PATH);
        }

        $relativeAssetPath = Path::canonicalize($relativeAssetPath);
        if (
            $relativeAssetPath === ''
            || $relativeAssetPath === '..'
            || str_starts_with($relativeAssetPath, '../')
            || Path::isAbsolute($relativeAssetPath)
        ) {
            return $this->createViolation($bundle, $assetType, $assetUrl, null, self::REASON_INVALID_PATH);
        }

        $administrationPublicPath = Path::join($bundle->getPath(), self::ADMINISTRATION_PUBLIC_PATH);
        $expectedFilePath = Path::join($administrationPublicPath, $relativeAssetPath);

        if (!Path::isBasePath($administrationPublicPath, $expectedFilePath) || $expectedFilePath === $administrationPublicPath) {
            return $this->createViolation($bundle, $assetType, $assetUrl, $expectedFilePath, self::REASON_INVALID_PATH);
        }

        if (!is_file($expectedFilePath)) {
            return $this->createViolation($bundle, $assetType, $assetUrl, $expectedFilePath, self::REASON_MISSING);
        }

        return null;
    }

    private function createViolation(
        Bundle $bundle,
        string $assetType,
        string $assetUrl,
        ?string $expectedFilePath,
        string $reason,
    ): AdministrationExtensionAssetViolation {
        return new AdministrationExtensionAssetViolation(
            $bundle->getName(),
            $this->getTechnicalBundleName($bundle),
            $assetType,
            $assetUrl,
            $expectedFilePath,
            $this->getEntrypointsFilePath($bundle),
            $reason,
        );
    }
}
