<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UpdateHtaccess implements EventSubscriberInterface
{
    private const MARKER_START = '# BEGIN Shopware';
    private const MARKER_STOP = '# END Shopware';
    private const INSTRUCTIONS = '# The directives (lines) between "# BEGIN Shopware" and "# END Shopware" are dynamically generated. Any changes to the directives between these markers will be overwritten.';

    private const OLD_FILES = [
        '9ab5be8c4bbff3490f3ae367af8a30d7', // https://github.com/shopware/production/commit/bebf9adc90bf5d7b0d53a149cc5bdba328696086
        'ba812f2a64b337b032b10685ca6e2308', // https://github.com/shopware/production/commit/18ce6ffc904b8d2d237dc4ee6654c1fa9a6df719
    ];

    private string $htaccessPath;

    public function __construct(string $htaccessPath)
    {
        $this->htaccessPath = $htaccessPath;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UpdatePostFinishEvent::class => 'update',
        ];
    }

    public function update(): void
    {
        if (!file_exists($this->htaccessPath) || !file_exists($this->htaccessPath . '.dist')) {
            return;
        }

        if (\in_array(md5_file($this->htaccessPath), self::OLD_FILES, true)) {
            $this->replaceFile($this->htaccessPath);

            return;
        }

        $content = file_get_contents($this->htaccessPath);

        // User has deleted the markers. So we will ignore the update process
        if (strpos($content, self::MARKER_START) === false || strpos($content, self::MARKER_STOP) === false) {
            return;
        }

        $this->updateByMarkers($this->htaccessPath);
    }

    /**
     * Replace entire .htaccess from dist
     */
    private function replaceFile(string $path): void
    {
        $dist = $path . '.dist';

        if (!file_exists($dist)) {
            return;
        }

        $perms = fileperms($dist);
        copy($dist, $path);

        if ($perms) {
            chmod($path, $perms | 0644);
        }
    }

    private function updateByMarkers(string $path): void
    {
        [$pre, $_, $post] = $this->getLinesFromMarkedFile($path);
        [$_, $existing, $_] = $this->getLinesFromMarkedFile($path . '.dist');

        if (!\in_array(self::INSTRUCTIONS, $existing, true)) {
            array_unshift($existing, self::INSTRUCTIONS);
        }

        array_unshift($existing, self::MARKER_START);
        $existing[] = self::MARKER_STOP;

        $newFile = implode("\n", array_merge($pre, $existing, $post));

        $perms = fileperms($path);
        file_put_contents($path, $newFile);

        if ($perms) {
            chmod($path, $perms | 0644);
        }
    }

    private function getLinesFromMarkedFile(string $path): array
    {
        $fp = fopen($path, 'rb+');
        if (!$fp) {
            return [];
        }

        $lines = [];
        while (!feof($fp)) {
            if ($line = fgets($fp)) {
                $lines[] = rtrim($line, "\r\n");
            }
        }

        $foundStart = false;
        $foundStop = false;
        $preLines = [];
        $postLines = [];
        $existingLines = [];

        foreach ($lines as $line) {
            if (!$foundStart && strpos($line, self::MARKER_START) === 0) {
                $foundStart = true;

                continue;
            }

            if (!$foundStop && strpos($line, self::MARKER_STOP) === 0) {
                $foundStop = true;

                continue;
            }

            if (!$foundStart) {
                $preLines[] = $line;
            } elseif ($foundStop) {
                $postLines[] = $line;
            } else {
                $existingLines[] = $line;
            }
        }

        return [$preLines, $existingLines, $postLines];
    }
}
