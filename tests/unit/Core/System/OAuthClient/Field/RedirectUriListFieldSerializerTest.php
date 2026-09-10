<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\OAuthClient\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\OAuthClient\Field\RedirectUriListField;
use Shopware\Core\System\OAuthClient\Field\RedirectUriListFieldSerializer;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RedirectUriListFieldSerializer::class)]
class RedirectUriListFieldSerializerTest extends TestCase
{
    private RedirectUriListFieldSerializer $serializer;

    private RedirectUriListField $field;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $this->serializer = new RedirectUriListFieldSerializer(
            Validation::createValidator(),
            static::createStub(DefinitionInstanceRegistry::class),
        );
        $this->field = (new RedirectUriListField('redirect_uris', 'redirectUris'))->addFlags(new Required());
        $this->parameters = static::createStub(WriteParameterBag::class);
        $this->parameters->method('getPath')->willReturn('/0');
    }

    /**
     * @param list<string> $uris
     */
    #[DataProvider('validUris')]
    public function testPreservesValidUrlsExactly(array $uris): void
    {
        $encoded = iterator_to_array($this->serializer->encode(
            $this->field,
            EntityExistence::createEmpty(),
            new KeyValuePair('redirectUris', $uris, true),
            $this->parameters,
        ));

        static::assertSame($uris, json_decode($encoded['redirect_uris'], true, flags: \JSON_THROW_ON_ERROR));
        static::assertSame($uris, $this->serializer->decode($this->field, $encoded['redirect_uris']));
    }

    /**
     * @return \Generator<string, array{list<string>}>
     */
    public static function validUris(): \Generator
    {
        yield 'HTTPS URL' => [['https://example.com/callback']];
        yield 'IPv4 loopback with dynamic port' => [['http://127.0.0.1:54321/callback']];
        yield 'IPv6 loopback with dynamic port' => [['http://[::1]:54321/callback']];
        yield 'loopback without port' => [['http://127.0.0.1/callback', 'http://[::1]/callback']];
        yield 'case and escaping are not normalized' => [['https://EXAMPLE.com/Callback?x=%2f&y=%2F']];
        yield 'at sign in path is not a credential' => [['https://example.com/@callback']];
        yield 'maximum URL length' => [['https://example.com/' . str_repeat('a', 2028)]];
        yield 'maximum list size' => [array_fill(0, 20, 'https://example.com/callback')];
    }

    #[DataProvider('invalidUris')]
    public function testRejectsInvalidValuesWithFieldPointers(mixed $uris, string $pointer): void
    {
        try {
            iterator_to_array($this->serializer->encode(
                $this->field,
                EntityExistence::createEmpty(),
                new KeyValuePair('redirectUris', $uris, true),
                $this->parameters,
            ));
            static::fail('Invalid redirect URLs must fail during field serialization.');
        } catch (WriteConstraintViolationException $exception) {
            $errors = iterator_to_array($exception->getErrors());
            static::assertCount(1, $errors);
            static::assertSame($pointer, $errors[0]['source']['pointer']);
        }
    }

    /**
     * @return \Generator<string, array{mixed, string}>
     */
    public static function invalidUris(): \Generator
    {
        yield 'required null' => [null, '/0/redirectUris'];
        yield 'empty list' => [[], '/0/redirectUris'];
        yield 'too many URLs' => [array_fill(0, 21, 'https://example.com/callback'), '/0/redirectUris'];
        yield 'scalar instead of list' => ['https://example.com', '/0/redirectUris'];
        yield 'associative map instead of list' => [['url' => 'https://example.com'], '/0/redirectUris'];
        yield 'non-string element' => [[42], '/0/redirectUris/0'];
        yield 'nested list' => [[['https://example.com']], '/0/redirectUris/0'];
        yield 'null element' => [[null], '/0/redirectUris/0'];
        yield 'empty element' => [[''], '/0/redirectUris/0'];
        yield 'whitespace is not trimmed' => [[' https://example.com'], '/0/redirectUris/0'];
        yield 'embedded control character' => [["https://example.com/callback\n"], '/0/redirectUris/0'];
        yield 'relative URL' => [['/callback'], '/0/redirectUris/0'];
        yield 'network path reference' => [['//example.com/callback'], '/0/redirectUris/0'];
        yield 'non-loopback HTTP' => [['http://example.com/callback'], '/0/redirectUris/0'];
        yield 'localhost is not a loopback literal' => [['http://localhost/callback'], '/0/redirectUris/0'];
        yield 'loopback lookalike hostname' => [['http://127.0.0.1.example.com/callback'], '/0/redirectUris/0'];
        yield 'unsupported scheme' => [['javascript:alert(1)'], '/0/redirectUris/0'];
        yield 'custom scheme is not supported yet' => [['my-app://callback'], '/0/redirectUris/0'];
        yield 'credentials' => [['https://user:password@example.com/callback'], '/0/redirectUris/0'];
        yield 'empty user info' => [['https://@example.com/callback'], '/0/redirectUris/0'];
        yield 'fragment' => [['https://example.com/callback#fragment'], '/0/redirectUris/0'];
        yield 'empty fragment' => [['https://example.com/callback#'], '/0/redirectUris/0'];
        yield 'wildcard path' => [['https://example.com/*'], '/0/redirectUris/0'];
        yield 'backslash' => [['https://example.com/\\callback'], '/0/redirectUris/0'];
        yield 'invalid port' => [['https://example.com:99999/callback'], '/0/redirectUris/0'];
        yield 'URL exceeds length limit' => [['https://example.com/' . str_repeat('a', 2030)], '/0/redirectUris/0'];
        yield 'pointer identifies second URL' => [['https://example.com', 'http://example.com'], '/0/redirectUris/1'];
    }

    public function testOptionalNullIsPreserved(): void
    {
        $field = new RedirectUriListField('redirect_uris', 'redirectUris');

        static::assertSame(['redirect_uris' => null], iterator_to_array($this->serializer->encode(
            $field,
            EntityExistence::createEmpty(),
            new KeyValuePair('redirectUris', null, true),
            $this->parameters,
        )));
    }
}
