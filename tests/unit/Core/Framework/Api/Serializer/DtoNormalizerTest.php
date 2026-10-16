<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Api\Serializer\DtoNormalizer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DtoNormalizer::class)]
class DtoNormalizerTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        $metadata = new ClassMetadataFactory(new AttributeLoader());
        $this->serializer = new Serializer([
            new DtoNormalizer(new PropertyNormalizer($metadata, new MetadataAwareNameConverter($metadata), new ReflectionExtractor())),
        ], [new JsonEncoder()]);
    }

    /**
     * @param array<string, string|null> $data
     */
    #[DataProvider('presenceCases')]
    public function testRequestMappingPreservesPresence(array $data, bool $valid): void
    {
        $request = Request::create('/test', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($data, \JSON_THROW_ON_ERROR));
        $resolver = new RequestPayloadValueResolver($this->serializer, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
        $argument = new ArgumentMetadata('dto', PresenceDto::class, false, false, null, false, [new MapRequestPayload()]);
        $event = new ControllerArgumentsEvent(
            static::createStub(HttpKernelInterface::class),
            static function (): void {},
            iterator_to_array($resolver->resolve($request, $argument)),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        if (!$valid) {
            $this->expectException(HttpExceptionInterface::class);
        }

        $resolver->onKernelControllerArguments($event);

        if ($valid) {
            $dto = $event->getArguments()[0];
            static::assertInstanceOf(PresenceDto::class, $dto);
            $properties = get_object_vars($dto);
            ksort($properties);
            ksort($data);
            static::assertSame($data, $properties);
            static::assertJsonStringEqualsJsonString(json_encode($data, \JSON_THROW_ON_ERROR), $this->serializer->serialize($dto, 'json'));
        }
    }

    /**
     * @return iterable<string, array{array<string, string|null>, bool}>
     */
    public static function presenceCases(): iterable
    {
        $required = ['requiredValue' => 'value', 'requiredNullable' => 'value'];
        yield 'required non-nullable missing' => [['requiredNullable' => 'value'], false];
        yield 'required non-nullable null' => [['requiredValue' => null, 'requiredNullable' => 'value'], false];
        yield 'required nullable missing' => [['requiredValue' => 'value'], false];
        yield 'required nullable null' => [['requiredValue' => 'value', 'requiredNullable' => null], true];
        yield 'required values and optional fields missing' => [$required, true];
        yield 'optional non-nullable null' => [[...$required, 'optionalValue' => null], false];
        yield 'optional non-nullable value' => [[...$required, 'optionalValue' => 'value'], true];
        yield 'optional nullable null' => [[...$required, 'optionalNullable' => null], true];
        yield 'optional nullable value' => [[...$required, 'optionalNullable' => 'value'], true];
    }

    public function testPublicPropertiesPreserveOmission(): void
    {
        $dto = new PresenceDto(requiredValue: 'value', requiredNullable: null);
        $dto->optionalNullable = null;

        static::assertArrayNotHasKey('optionalValue', get_object_vars($dto));
        static::assertArrayHasKey('optionalNullable', get_object_vars($dto));
        static::assertJsonStringEqualsJsonString(
            '{"requiredValue":"value","requiredNullable":null,"optionalNullable":null}',
            $this->serializer->serialize($dto, 'json'),
        );
    }

    public function testNestedDtoPreservesNullWithoutExposingResponseMetadata(): void
    {
        $nested = new PresenceDto('value', null);
        $nested->optionalNullable = null;
        $response = new class($nested) extends AbstractResponse {
            public function __construct(public PresenceDto $nested)
            {
                parent::__construct();
            }
        };
        $response->setHeader('X-Test', 'private metadata');

        static::assertJsonStringEqualsJsonString(
            '{"nested":{"requiredValue":"value","requiredNullable":null,"optionalNullable":null}}',
            $this->serializer->serialize($response, 'json'),
        );
    }

    public function testSerializedNamesRoundTrip(): void
    {
        $data = [
            'total-count-mode' => 2,
            'post-filter' => 'filter',
            'camelCase' => 'unchanged',
            'snake_case' => 'snake',
            'quote\'field' => 'quoted',
        ];

        $dto = $this->serializer->denormalize($data, WireNamesDto::class);
        static::assertInstanceOf(WireNamesDto::class, $dto);
        static::assertSame(2, $dto->totalCountMode);
        static::assertSame('filter', $dto->postFilter);
        static::assertJsonStringEqualsJsonString(json_encode($data, \JSON_THROW_ON_ERROR), $this->serializer->serialize($dto, 'json'));
    }

    public function testDoesNotHandleOtherObjects(): void
    {
        $normalizer = new DtoNormalizer(new PropertyNormalizer());

        static::assertFalse($normalizer->supportsNormalization(new \stdClass()));
        static::assertFalse($normalizer->supportsDenormalization([], \stdClass::class));
    }

    public function testEmptyExtensionsRemainAbsent(): void
    {
        $dto = new PresenceDto('value', null);
        $dto->removeExtension('missing');
        $dto->setExtensions([]);
        $dto->addExtension('custom', ['value' => 'test']);
        $dto->removeExtension('custom');

        static::assertSame([], $dto->getExtensions());
        static::assertJsonStringEqualsJsonString(
            '{"requiredValue":"value","requiredNullable":null}',
            $this->serializer->serialize($dto, 'json'),
        );
    }
}

/**
 * @internal
 */
#[Package('framework')]
final class PresenceDto extends AbstractDto
{
    public string $optionalValue;

    public ?string $optionalNullable;

    public function __construct(
        #[Assert\NotBlank]
        public string $requiredValue,
        public ?string $requiredNullable,
    ) {
    }
}

/**
 * @internal
 */
#[Package('framework')]
final class WireNamesDto extends AbstractDto
{
    #[SerializedName('post-filter')]
    public string $postFilter;

    public string $camelCase;

    #[SerializedName('snake_case')]
    public string $snakeCase;

    #[SerializedName('quote\'field')]
    public string $quoteField;

    public function __construct(
        #[SerializedName('total-count-mode')]
        public int $totalCountMode,
    ) {
    }
}
