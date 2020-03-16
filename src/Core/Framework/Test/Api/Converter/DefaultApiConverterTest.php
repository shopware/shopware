<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Converter\DefaultApiConverter;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\Api\Converter\fixtures\DeprecatedDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class DefaultApiConverterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testFieldNotRemovedInBeforeVersion(): void
    {
        $converter = new DefaultApiConverter($this->getDefinitionRegistry(), $this->getRequestStack());

        static::assertFalse($converter->isDeprecated(1, 'deprecated', 'price'));
        static::assertFalse($converter->isDeprecated(1, 'deprecated', 'taxId'));
        static::assertFalse($converter->isDeprecated(1, 'deprecated', 'tax'));
    }

    public function testFieldIsRemovedInRightVersion(): void
    {
        $converter = new DefaultApiConverter($this->getDefinitionRegistry(), $this->getRequestStack());

        static::assertTrue($converter->isDeprecated(2, 'deprecated', 'price'));
        static::assertTrue($converter->isDeprecated(2, 'deprecated', 'taxId'));
        static::assertTrue($converter->isDeprecated(2, 'deprecated', 'tax'));
    }

    public function testFieldIsIgnoredWithVersionHeader(): void
    {
        $converter = new DefaultApiConverter($this->getDefinitionRegistry(), $this->getRequestStack(true));

        static::assertFalse($converter->isDeprecated(2, 'deprecated', 'price'));
        static::assertFalse($converter->isDeprecated(2, 'deprecated', 'taxId'));
        static::assertFalse($converter->isDeprecated(2, 'deprecated', 'tax'));
    }

    private function getDefinitionRegistry(): DefinitionInstanceRegistry
    {
        $definition = new DeprecatedDefinition();
        $definition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $instanceRegistery = $this->createMock(DefinitionInstanceRegistry::class);
        $instanceRegistery->method('getByEntityName')->willReturn($definition);
        $instanceRegistery->method('getDefinitions')->willReturn([$definition]);

        return $instanceRegistery;
    }

    private function getRequestStack(bool $ignoreDeprecations = false): RequestStack
    {
        $requestStack = new RequestStack();
        $request = new Request();

        if ($ignoreDeprecations) {
            $request->headers->set(PlatformRequest::HEADER_IGNORE_DEPRECATIONS, 'true');
        }

        $requestStack->push($request);

        return $requestStack;
    }
}
