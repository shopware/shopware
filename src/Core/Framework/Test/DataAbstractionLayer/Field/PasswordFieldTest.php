<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Component\Validator\Constraints\NotBlank;

class PasswordFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var PasswordField
     */
    private $field;

    public function setUp()
    {
        $this->field = new PasswordField('password', 'password');
        $this->field->setConstraintBuilder($this->getContainer()->get(ConstraintBuilder::class));
        $this->field->setValidator($this->getContainer()->get('validator'));
    }

    public function testGetStorage(): void
    {
        static::assertEquals('password', $this->field->getStorageName());
    }

    public function testNullableField(): void
    {
        $field = $this->field;
        $existence = new EntityExistence(UserDefinition::class, [], false, false, false, []);
        $kvPair = new KeyValuePair('password', null, true);

        $payload = iterator_to_array($field($existence, $kvPair));

        static::assertEquals($kvPair->getValue(), $payload['password']);
    }

    public function testEncoding(): void
    {
        $field = $this->field;
        $existence = new EntityExistence(UserDefinition::class, [], false, false, false, []);
        $kvPair = new KeyValuePair('password', 'shopware', true);

        $payload = iterator_to_array($field($existence, $kvPair));

        static::assertNotEquals($kvPair->getValue(), $payload['password']);
        static::assertTrue(password_verify($kvPair->getValue(), $payload['password']));
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

        static::assertInstanceOf(InvalidFieldException::class, $exception);
        static::assertNotNull($exception->getViolations()->findByCodes(NotBlank::IS_BLANK_ERROR));
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

        static::assertInstanceOf(InvalidFieldException::class, $exception);
        static::assertNotNull($exception->getViolations()->findByCodes(NotBlank::IS_BLANK_ERROR));
    }
}
