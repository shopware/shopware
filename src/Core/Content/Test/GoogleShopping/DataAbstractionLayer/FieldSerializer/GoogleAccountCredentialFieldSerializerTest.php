<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\Field\GoogleAccountCredentialField;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\FieldSerializer\GoogleAccountCredentialFieldSerializer;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\JsonDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use function Flag\skipTestNext6050;

class GoogleAccountCredentialFieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var GoogleAccountCredentialFieldSerializer
     */
    private $serializer;

    /**
     * @var GoogleAccountCredentialField
     */
    private $field;

    /**
     * @var EntityExistence
     */
    private $existence;

    /**
     * @var WriteParameterBag
     */
    private $parameters;

    protected function setUp(): void
    {
        skipTestNext6050($this);

        $this->serializer = $this->getContainer()->get(GoogleAccountCredentialFieldSerializer::class);
        $this->field = new GoogleAccountCredentialField('data', 'data');
        $this->field->addFlags(new Required());

        $definition = $this->registerDefinition(JsonDefinition::class);
        $this->existence = new EntityExistence($definition->getEntityName(), [], false, false, false, []);

        $this->parameters = new WriteParameterBag(
            $definition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }

    public function testEncodeWithApiParam(): void
    {
        $credential = $this->initCredential();

        $kvPair = new KeyValuePair('data', $credential, true);
        $encoded = $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();

        static::assertEquals($encoded, JsonFieldSerializer::encodeJson($credential));
    }

    public function testEncodeDecoceWithInstanceObject(): void
    {
        $credential = $this->initCredential();

        $googleShoppingAccountCredential = new GoogleAccountCredential($credential);

        $kvPair = new KeyValuePair('data', $googleShoppingAccountCredential, true);
        $encoded = $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();

        /** @var GoogleAccountCredential $decoded */
        $decoded = $this->serializer->decode($this->field, $encoded);

        static::assertEquals($googleShoppingAccountCredential, $decoded);
    }

    public function testEmptyValueForRequiredField(): void
    {
        $this->expectException(WriteConstraintViolationException::class);

        $kvPair = new KeyValuePair('data', [], true);

        $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
    }

    private function initCredential(): array
    {
        $credential = [
            'access_token' => 'ya29.a0Adw1xeW4xei7do9ByIQaiPkxjw617yU1pAvYXRn',
            'refresh_token' => '1//0gTTgzGwplfyTCgYIARAAGBASNwF-L9Ir_K8q5k3l5M0ouz4hdlQ4hoE2vrqejreIjA',
            'created' => 1585199421,
            'id_token' => 'eyJhbGciOiJSUzI1NiIsImtpZCI6IjUzYzY2YWFiNTBjZmRkOTFhMTQzNTBhNjY0ODJkYjM4MDBj',
            'scope' => 'https://www.googleapis.com/auth/content https://www.googleapis.com/auth/adwords',
            'expires_in' => 3599,
        ];

        return $credential;
    }
}
