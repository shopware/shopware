<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Acl;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclAnnotationValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Test\Api\Acl\fixtures\AclTestController;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class AclAnnotationValidatorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @dataProvider annotationProvider
     */
    public function testValidateRequest(array $privileges, array $acl, bool $pass): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($privileges);

        $context = new Context(
            $source,
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $request = new Request();
        $request->attributes->set('_acl', new Acl(['value' => $acl]));
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $validator = new AclAnnotationValidator();

        $kernel = $this->createMock(Kernel::class);

        $exception = null;

        try {
            $validator->validate(new ControllerEvent($kernel, [new AclTestController(), 'testRoute'], $request, 1));
        } catch (\Exception $e) {
            $exception = $e;
        }

        if ($pass) {
            static::assertNull($exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
        } else {
            static::assertInstanceOf(MissingPrivilegeException::class, $exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
        }
    }

    public function annotationProvider()
    {
        return [
            [
                // privs of user   //acl   // should pass?
                ['product:write'], [], true,
            ],
            [
                [], ['productWrite'], false,
            ],
            [
                ['product:write'], ['product:write'], true,
            ],
            [
                ['product:write', 'product:read'], ['product:write', 'product:read'], true,
            ],
            [
                ['product:write'], ['product:write', 'product:read'], false,
            ],
            [
                ['api.test.route'], ['api.test.route'], true,
            ],
            [
                [], ['api.test.route'], false,
            ],
            [
                ['product:write', 'product:read'], ['api.test.route'], false,
            ],
        ];
    }
}
