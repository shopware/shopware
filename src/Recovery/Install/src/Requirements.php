<?php declare(strict_types=1);

namespace Shopware\Recovery\Install;

use Shopware\Recovery\Install\Service\TranslationService;

class Requirements
{
    /**
     * @var string
     */
    private $sourceFile;

    /**
     * @var TranslationService
     */
    private $translator;

    /**
     * @param string $sourceFile
     */
    public function __construct($sourceFile, TranslationService $translator)
    {
        if (!is_readable($sourceFile)) {
            throw new \RuntimeException(sprintf('Cannot read requirements file in %s.', $sourceFile));
        }

        $this->sourceFile = $sourceFile;
        $this->translator = $translator;
    }

    public function toArray(): array
    {
        $result = [
            'hasErrors' => false,
            'hasWarnings' => false,
            'checks' => [],
            'phpVersionNotSupported' => false,
        ];

        $checks = [];
        foreach ($this->runChecks() as $requirement) {
            $check = [];

            $name = (string) $requirement['name'];
            if ($name === 'mod_rewrite' && PHP_SAPI === 'cli') {
                continue;
            }

            // Skip database checks because we don't have a db connection yet
            if (isset($requirement['database'])) {
                continue;
            }

            if ($name === 'mod_rewrite' && isset($_SERVER['SERVER_SOFTWARE']) && mb_stripos($_SERVER['SERVER_SOFTWARE'], 'apache') === false) {
                continue;
            }

            $check['name'] = (string) $requirement['name'];
            $check['group'] = (string) $requirement['group'];
            $check['notice'] = $requirement['notice'] ?? '';
            $check['required'] = (string) $requirement['required'];
            $check['version'] = (string) $requirement['version'];
            $check['maxCompatibleVersion'] = $requirement['maxCompatibleVersion'] ?? '';
            $check['check'] = (bool) ($requirement['result'] ?? false);
            $check['error'] = (bool) ($requirement['error'] ?? false);

            if ($check['maxCompatibleVersion'] && $check['check']) {
                $check = $this->handleMaxCompatibleVersion($check);
                if ($check['notice']) {
                    $result['phpVersionNotSupported'] = $check['notice'];
                }
            }

            $checks[] = $check;
        }

        $checks = array_merge($checks, $this->checkOpcache());

        foreach ($checks as $check) {
            if (!$check['check'] && $check['error']) {
                $check['status'] = 'error';
                $result['hasErrors'] = true;
            } elseif (!$check['check']) {
                $check['status'] = 'warning';
                $result['hasWarnings'] = true;
            } else {
                $check['status'] = 'ok';
            }
            unset($check['check'], $check['error']);

            $result['checks'][] = $check;
        }

        return $result;
    }

    /**
     * Checks a requirement
     *
     * @param string $name
     *
     * @return bool|string|int|null
     */
    private function getRuntimeValue($name)
    {
        $m = 'check' . str_replace(' ', '', ucwords(str_replace(['_', '.'], ' ', $name)));
        if (method_exists($this, $m)) {
            return $this->$m();
        }

        if (extension_loaded($name)) {
            return true;
        }

        if (function_exists($name)) {
            return true;
        }

        if (($value = ini_get($name)) !== false) {
            $value = (string) $value;

            if (mb_strtolower($value) === 'off') {
                return false;
            }

            if (mb_strtolower($value) === 'on') {
                return true;
            }

            return $value;
        }

        return null;
    }

    /**
     * Returns the check list
     *
     * @return array
     */
    private function runChecks()
    {
        $checks = include $this->sourceFile;

        $requirements = [];

        foreach ($checks['system'] as $requirement) {
            $name = (string) $requirement['name'];
            $value = $this->getRuntimeValue($name);
            $requirement['result'] = $this->compare(
                $name,
                $value,
                (string) $requirement['required']
            );
            $requirement['version'] = $value;

            $requirements[] = $requirement;
        }

        return $requirements;
    }

    /**
     * Compares the requirement with the version
     *
     * @param string           $name
     * @param bool|string|null $value
     * @param string           $requiredValue
     *
     * @return bool
     */
    private function compare($name, $value, $requiredValue)
    {
        $m = 'compare' . str_replace(' ', '', ucwords(str_replace(['_', '.'], ' ', $name)));
        if (method_exists($this, $m)) {
            return $this->$m($value, $requiredValue);
        }

        if (!is_string($value)) {
            return (string) $requiredValue === (string) $value;
        }

        if (preg_match('#^[0-9]+[A-Z]$#', $requiredValue)) {
            return $this->decodePhpSize($requiredValue) <= $this->decodePhpSize($value);
        }

        if (preg_match('#^[0-9]+ [A-Z]+$#i', $requiredValue)) {
            return $this->decodeSize($requiredValue) <= $this->decodeSize($value);
        }

        if (preg_match('#^[0-9][0-9\.]+$#', $requiredValue)) {
            return version_compare($requiredValue, $value, '<=');
        }

        return (string) $requiredValue === (string) $value;
    }

    /**
     * Checks the php version
     *
     * @return bool|string
     */
    private function checkPhp()
    {
        if (mb_strpos(PHP_VERSION, '-')) {
            return mb_substr(PHP_VERSION, 0, mb_strpos(PHP_VERSION, '-'));
        }

        return PHP_VERSION;
    }

    /**
     * Checks the php version
     *
     * @return bool
     */
    private function checkModRewrite()
    {
        return isset($_SERVER['MOD_REWRITE']);
    }

    /**
     * Checks the opcache configuration if the opcache exists.
     */
    private function checkOpcache()
    {
        if (!extension_loaded('Zend OPcache')) {
            return [];
        }

        $useCwdOption = $this->compare('opcache.use_cwd', ini_get('opcache.use_cwd'), '1');
        $opcacheRequirements = [[
            'name' => 'opcache.use_cwd',
            'group' => 'core',
            'required' => 1,
            'version' => ini_get('opcache.use_cwd'),
            'result' => ini_get('opcache.use_cwd'),
            'notice' => '',
            'check' => $this->compare('opcache.use_cwd', ini_get('opcache.use_cwd'), '1'),
            'error' => '',
        ]];

        if (fileinode('/') > 2) {
            $validateRootOption = $this->compare('opcache.validate_root', ini_get('opcache.validate_root'), '1');
            $opcacheRequirements[] = [
                'name' => 'opcache.validate_root',
                'group' => 'core',
                'required' => 1,
                'version' => ini_get('opcache.validate_root'),
                'result' => ini_get('opcache.validate_root'),
                'notice' => '',
                'check' => $this->compare('opcache.validate_root', ini_get('opcache.validate_root'), '1'),
                'error' => '',
            ];
        }

        return $opcacheRequirements;
    }

    /**
     * Checks the curl version
     *
     * @return bool|string
     */
    private function checkCurl()
    {
        if (function_exists('curl_version')) {
            $curl = curl_version();

            return $curl['version'];
        } elseif (function_exists('curl_init')) {
            return true;
        }

        return false;
    }

    /**
     * Checks the lib xml version
     *
     * @return bool|string
     */
    private function checkLibXml()
    {
        if (defined('LIBXML_DOTTED_VERSION')) {
            return LIBXML_DOTTED_VERSION;
        }

        return false;
    }

    /**
     * Checks the gd version
     *
     * @return bool|string
     */
    private function checkGd()
    {
        if (function_exists('gd_info')) {
            $gd = gd_info();
            if (preg_match('#[0-9.]+#', $gd['GD Version'], $match)) {
                if (mb_substr_count($match[0], '.') === 1) {
                    $match[0] .= '.0';
                }

                return $match[0];
            }

            return $gd['GD Version'];
        }

        return false;
    }

    /**
     * Checks the gd jpg support
     *
     * @return bool|string
     */
    private function checkGdJpg()
    {
        if (function_exists('gd_info')) {
            $gd = gd_info();

            return !empty($gd['JPEG Support']) || !empty($gd['JPG Support']);
        }

        return false;
    }

    /**
     * Checks the freetype support
     *
     * @return bool|string
     */
    private function checkFreetype()
    {
        if (function_exists('gd_info')) {
            $gd = gd_info();

            return !empty($gd['FreeType Support']);
        }

        return false;
    }

    /**
     * Checks the session save path config
     *
     * @return bool|string
     */
    private function checkSessionSavePath()
    {
        if (function_exists('session_save_path')) {
            return (bool) session_save_path();
        } elseif (ini_get('session.save_path')) {
            return true;
        }

        return false;
    }

    /**
     * Checks the suhosin.get.max_value_length which limits the max get parameter length.
     *
     * @return int
     */
    private function checkSuhosinGetMaxValueLength()
    {
        $length = (int) ini_get('suhosin.get.max_value_length');
        if ($length === 0) {
            return 2000;
        }

        return $length;
    }

    /**
     * Checks the include path config
     *
     * @return bool
     */
    private function checkIncludePath()
    {
        $old = set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . DIRECTORY_SEPARATOR);

        return $old && get_include_path() !== $old;
    }

    /**
     * Compare max execution time config
     *
     * @param string $version
     * @param string $required
     *
     * @return bool
     */
    private function compareMaxExecutionTime($version, $required)
    {
        if (!$version) {
            return true;
        }

        return version_compare($required, $version, '<=');
    }

    /**
     * Decode php size format
     *
     * @param string $val
     *
     * @return float
     */
    private function decodePhpSize($val)
    {
        $val = mb_strtolower(trim($val));
        $last = mb_substr($val, -1);

        $val = (float) $val;
        switch ($last) {
            /* @noinspection PhpMissingBreakStatementInspection */
            case 'g':
                $val *= 1024;
            /* @noinspection PhpMissingBreakStatementInspection */
            // no break
            case 'm':
                $val *= 1024;
                // no break
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * Decode byte size format
     *
     * @param string $val
     *
     * @return float
     */
    private function decodeSize($val)
    {
        $val = trim($val);
        list($val, $last) = explode(' ', $val);
        $val = (float) $val;
        switch (mb_strtoupper($last)) {
            /* @noinspection PhpMissingBreakStatementInspection */
            case 'TB':
                $val *= 1024;
                // no break
            case 'GB':
                $val *= 1024;
                // no break
            case 'MB':
                $val *= 1024;
                // no break
            case 'KB':
                $val *= 1024;
                // no break
            case 'B':
                $val = (float) $val;
        }

        return $val;
    }

    /**
     * Encode byte size format
     *
     * @param float $bytes
     *
     * @return string
     */
    private function encodeSize($bytes)
    {
        $types = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes >= 1024 && $i < (count($types) - 1); $bytes /= 1024, $i++);

        return round($bytes, 2) . ' ' . $types[$i];
    }

    /**
     * @return array
     */
    private function handleMaxCompatibleVersion(array $check)
    {
        if (version_compare($check['version'], $check['maxCompatibleVersion'], '>')) {
            $check['check'] = false;
            $maxCompatibleVersion = str_replace('.99', '', $check['maxCompatibleVersion']);
            $key = 'requirements_php_max_compatible_version';

            $check['notice'] = sprintf($this->translator->translate($key), $maxCompatibleVersion);
        }

        return $check;
    }
}
