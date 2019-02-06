<?php declare(strict_types=1);

function getProjectDir(): string
{
    $r = new \ReflectionClass($_SERVER['KERNEL_CLASS']);
    $dir = $rootDir = \dirname($r->getFileName());
    while (!file_exists($dir . '/composer.json')) {
        if ($dir === \dirname($dir)) {
            return $rootDir;
        }
        $dir = \dirname($dir);
    }

    return $dir;
}

$projectDir = getProjectDir();

require_once $projectDir . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

if (!class_exists(Dotenv::class)) {
    throw new \RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
}
(new Dotenv())->load($projectDir . '/.env');

putenv('DATABASE_URL=' . getenv('DATABASE_URL') . '_test');
