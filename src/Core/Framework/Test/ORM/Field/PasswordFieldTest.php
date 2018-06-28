<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Field;

use Shopware\Core\Framework\ORM\Field\PasswordField;
use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Validation\ConstraintBuilder;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Constraints\NotBlank;

class PasswordFieldTest extends KernelTestCase
{
    /**
     * @var PasswordField
     */
    private $field;

    public function setUp()
    {
        parent::setUp();
        parent::bootKernel();

        $this->field = new PasswordField('password', 'password');
        $this->field->setConstraintBuilder(self::$container->get(ConstraintBuilder::class));
        $this->field->setValidator(self::$container->get('validator'));
    }

    public function testGetStorage(): void
    {
        $this->assertEquals('password', $this->field->getStorageName());
    }

    public function testNullableField(): void
    {
        $field = $this->field;
        $existence = new EntityExistence(UserDefinition::class, [], false, false, false, []);
        $kvPair = new KeyValuePair('password', null, true);

        $payload = iterator_to_array($field($existence, $kvPair));

        $this->assertEquals($kvPair->getValue(), $payload['password']);
    }

    public function testEncoding(): void
    {
        $field = $this->field;
        $existence = new EntityExistence(UserDefinition::class, [], false, false, false, []);
        $kvPair = new KeyValuePair('password', 'shopware', true);

        $payload = iterator_to_array($field($existence, $kvPair));

        $this->assertNotEquals($kvPair->getValue(), $payload['password']);
        $this->assertTrue(password_verify($kvPair->getValue(), $payload['password']));
    }

    public function testValueIsRequiredOnInsert(): void
    {
        $field = clone $this->field;
        $field->setFlags(new Required());

        $existence = new EntityExistence(UserDefinition::class, [], false, false, false, []);
        $kvPair = new KeyValuePair('password', null, true);

        $exception = null;
        try {
            iterator_to_array($field($existence, $kvPair));
        } catch (InvalidFieldException $exception) {
        }

        $this->assertInstanceOf(InvalidFieldException::class, $exception);
        $this->assertNotNull($exception->getViolations()->findByCodes(NotBlank::IS_BLANK_ERROR));
    }

    public function testValueIsRequiredOnUpdate(): void
    {
        $field = clone $this->field;
        $field->setFlags(new Required());

        $existence = new EntityExistence(UserDefinition::class, [], true, false, false, []);
        $kvPair = new KeyValuePair('password', null, true);

        $exception = null;
        try {
            iterator_to_array($field($existence, $kvPair));
        } catch (InvalidFieldException $exception) {
        }

        $this->assertInstanceOf(InvalidFieldException::class, $exception);
        $this->assertNotNull($exception->getViolations()->findByCodes(NotBlank::IS_BLANK_ERROR));
    }
}
