<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Source;
use Twig\Template;

use function Shopware\Core\Framework\Adapter\Twig\sw_get_attribute;

/**
 * @internal
 */
#[CoversFunction('Shopware\Core\Framework\Adapter\Twig\sw_get_attribute')]
class SwGetAttributeTest extends TestCase
{
    private MockObject&Environment $environmentMock;

    protected function setUp(): void
    {
        $this->environmentMock = $this->createMock(Environment::class);
        /** This is a fix for a autoload issue in the testsuite. Do not delete. */
        class_exists(CoreExtension::class);
    }

    public function testSwGetAttributeValueNull(): void
    {
        $object = new ArrayStruct(['test' => null]);
        $result = sw_get_attribute($this->environmentMock, new Source('', 'empty'), $object, 'test');

        static::assertEquals('', $result);
    }

    public function testSwGetAttributeValueBool(): void
    {
        $object = new ArrayStruct(['test' => true]);
        $result = sw_get_attribute($this->environmentMock, new Source('', 'empty'), $object, 'test');

        static::assertTrue($result);

        $object = new ArrayStruct(['test' => false]);
        $result = sw_get_attribute($this->environmentMock, new Source('', 'empty'), $object, 'test');

        static::assertFalse($result);
    }

    public function testSwGetAttributeJustProperty(): void
    {
        $object = new ArrayStruct(['test' => 'value']);
        $result = sw_get_attribute($this->environmentMock, new Source('', 'empty'), $object, 'test');

        static::assertEquals('value', $result);
    }

    public function testSwGetAttributeGetterMethods(): void
    {
        $object = new StructForTests();
        $object->setNoGetter(99);
        $object->setValue('valueValue');
        $object->setVisible(true);

        $result = sw_get_attribute($this->environmentMock, new Source('', 'empty'), $object, 'noGetter');

        static::assertNull($result);

        $result = sw_get_attribute($this->environmentMock, new Source('', 'empty'), $object, 'value');

        static::assertEquals('valueValue', $result);

        $result = sw_get_attribute($this->environmentMock, new Source('', 'empty'), $object, 'getValue');

        static::assertEquals('valueValue', $result);

        $result = sw_get_attribute($this->environmentMock, new Source('', 'empty'), $object, 'visible');

        static::assertTrue($result);

        $result = sw_get_attribute($this->environmentMock, new Source('', 'empty'), $object, 'isVisible');

        static::assertTrue($result);

        $result = sw_get_attribute(
            $this->environmentMock,
            new Source('', 'empty'),
            $object,
            'isVisible',
            [],
            Template::METHOD_CALL
        );

        static::assertTrue($result);
    }
}

/**
 * @internal
 */
class StructForTests extends Struct
{
    private bool $visible;

    private string $value;

    /**
     * @phpstan-ignore-next-line
     */
    private int $noGetter;

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        $this->visible = $visible;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function setNoGetter(int $noGetter): void
    {
        $this->noGetter = $noGetter;
    }
}
