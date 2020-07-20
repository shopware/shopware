<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\HttpKernel;
use Shopware\Core\Kernel;
use Symfony\Component\HttpFoundation\Request;

class HttpKernelTest extends TestCase
{
    public function testHandleSensitiveDataIsReplaced(): void
    {
        $kernel = $this->getHttpKernel();

        $_ENV['DATABASE_URL'] = str_replace('3306', '1111', $_ENV['DATABASE_URL']);
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

        $httpKernel = new HttpKernel('dev', true, \Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager::getClassLoader());

        return $httpKernel;
    }
}

class TestKernel extends Kernel
{
    public function __construct()
    {
        $urlParams = parse_url($_ENV['DATABASE_URL']);

        throw new DBALException(vsprintf(
            'Could not connect to the server as %s with the password %s with connection string %s',
            [$urlParams['user'], $urlParams['pass'], $_ENV['DATABASE_URL']]
        ));
    }

    public function getName()
    {
        return 'test_kernel';
    }

    public function getRootDir()
    {
        return __DIR__;
    }
}
