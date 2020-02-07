<?php declare(strict_types=1);

namespace Shopware\Core\Framework\FeatureFlag;

class FeatureFlagGenerator
{
    private const TEMPLATE_PHP = <<<'EOD'
<?php declare(strict_types=1);

namespace %s;

use %s;

/**
 * %s
 */

class %s extends FEATURE_FLAG
{
    public const NAME = '%s';
}


EOD;

    public function exportPhp(string $namespace, string $featureName, string $destinationPath, ?string $description = ''): string
    {
        $className = $this->getEnvironmentName($featureName);
        $featureFilePath = $destinationPath . "/$className.php";

        $contents = sprintf(
            self::TEMPLATE_PHP,
            $namespace,
            'Shopware\Core\Flag\FEATURE_FLAG',
            $this->phpSafe($description),
            $className,
            $className
        );

        file_put_contents($featureFilePath, $contents);

        return $featureFilePath;
    }

    public function getEnvironmentName(string $string): string
    {
        return 'FEATURE_' . $this->getNormalizedName($string);
    }

    private function getNormalizedName(string $string): string
    {
        return str_replace(' ', '_', mb_strtoupper(trim(preg_replace('/[^\da-z]/i', ' ', $string))));
    }

    private function phpSafe(?string $string): string
    {
        if (!is_string($string)) {
            return '';
        }

        return str_replace('*/', ' ', $string);
    }
}
