<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Acl;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclAnnotationValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;

class AclAnnotationValidatorTest extends TestCase
{
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
        $request->attributes->set('_acl', $acl);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $validator = new AclAnnotationValidator();

        $kernel = $this->createMock(Kernel::class);

        $exception = null;

        try {
            $validator->validate(new RequestEvent($kernel, $request, 1));
        } catch (\Exception $e) {
            $exception = $e;
        }

        if ($pass) {
            static::assertNull($exception);
        } else {
            static::assertInstanceOf(InsufficientAuthenticationException::class, $exception);
        }
    }

    public function annotationProvider()
    {
        return [
            [
                // privs of user   //annotation   // should pass?
                ['product:write'], [], true,
            ],
            [
                [], ['product:write'], false,
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
        ];
    }
}
