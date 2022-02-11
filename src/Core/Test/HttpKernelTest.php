<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\HttpKernel;
use Shopware\Core\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

class HttpKernelTest extends TestCase
{
    use EnvTestBehaviour;

    public function testHandleSensitiveDataIsReplaced(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => str_replace('3306', '1111', (string) EnvironmentHelper::getVariable('DATABASE_URL')),
        ]);
        $kernel = $this->getHttpKernel();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not connect to the server as ****** with the password ****** with connection string ******');

        $kernel->handle(Request::createFromGlobals());
    }

    private function getHttpKernel(): HttpKernel
    {
        $httpKernelReflection = new \ReflectionClass(HttpKernel::class);
        $reflectedProperty = $httpKernelReflection->getProperty('kernelClass');
        $reflectedProperty->setAccessible(true);
        $reflectedProperty->setValue(TestKernel::class);

        return new HttpKernel('dev', true, KernelLifecycleManager::getClassLoader());
    }
}

/**
 * @method void configureContainer(ContainerBuilder $container, LoaderInterface $loader)
 */
class TestKernel extends Kernel
{
    public function __construct()
    {
        $urlParams = parse_url($_ENV['DATABASE_URL']);
        if ($urlParams === false || !\array_key_exists('user', $urlParams) || !\array_key_exists('pass', $urlParams)) {
            throw new DBALException('Could not parse DATABASE_URL');
        }

        throw new DBALException(vsprintf(
            'Could not connect to the server as %s with the password %s with connection string %s',
            [$urlParams['user'], $urlParams['pass'], $_ENV['DATABASE_URL']]
        ));
    }

    public function getName(): string
    {
        return 'test_kernel';
    }

    public function getRootDir(): string
    {
        return __DIR__;
    }
}
