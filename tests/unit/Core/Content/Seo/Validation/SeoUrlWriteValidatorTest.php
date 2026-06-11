<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfo;
use Shopware\Core\Content\Seo\Validation\SeoUrlWriteValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlWriteValidator::class)]
class SeoUrlWriteValidatorTest extends TestCase
{
    private WriteContext $context;

    private SeoUrlDefinition $seoUrlDefinition;

    private SalesChannelDefinition $salesChannelDefinition;

    protected function setUp(): void
    {
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());

        $registry = new StaticDefinitionInstanceRegistry(
            [SeoUrlDefinition::class, SalesChannelDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $seoUrlDefinition = $registry->get(SeoUrlDefinition::class);
        static::assertInstanceOf(SeoUrlDefinition::class, $seoUrlDefinition);
        $this->seoUrlDefinition = $seoUrlDefinition;

        $salesChannelDefinition = $registry->get(SalesChannelDefinition::class);
        static::assertInstanceOf(SalesChannelDefinition::class, $salesChannelDefinition);
        $this->salesChannelDefinition = $salesChannelDefinition;
    }

    public function testSubscribedEvents(): void
    {
        $events = SeoUrlWriteValidator::getSubscribedEvents();

        static::assertSame(['preValidate'], array_values($events));
        static::assertArrayHasKey(PreWriteValidationEvent::class, $events);
    }

    public function testIgnoresOtherEntities(): void
    {
        $command = new InsertCommand(
            $this->salesChannelDefinition,
            ['name' => 'channel'],
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0/'
        );

        $event = new PreWriteValidationEvent($this->context, [$command]);
        (new SeoUrlWriteValidator())->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testPayloadWithoutSeoPathInfoPasses(): void
    {
        $command = new UpdateCommand(
            $this->seoUrlDefinition,
            ['is_canonical' => 1],
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0/'
        );

        $event = new PreWriteValidationEvent($this->context, [$command]);
        (new SeoUrlWriteValidator())->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validSeoPaths(): iterable
    {
        yield 'simple path' => ['Computers/Laptops'];
        yield 'hyphen and digits' => ['Pepper-white-ground-pearl/SW10098'];
        yield 'query string' => ['foo/bar?x=1'];
        yield 'valid percent-escape' => ['caf%C3%A9/SW10098'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidSeoPaths(): iterable
    {
        yield 'percent reported in #13796' => ['seo/url%/1'];
        yield 'fragment' => ['foo/bar#baz'];
        yield 'backslash' => ['foo\\bar'];
    }

    #[DataProvider('validSeoPaths')]
    public function testValidSeoPathPasses(string $path): void
    {
        $event = $this->dispatch($path);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[DataProvider('invalidSeoPaths')]
    public function testInvalidSeoPathIsRejected(string $path): void
    {
        $event = $this->dispatch($path);

        $thrown = null;

        try {
            $event->getExceptions()->tryToThrow();
        } catch (WriteException $e) {
            $thrown = $e;
        }

        static::assertNotNull($thrown, \sprintf('Expected WriteException for path "%s"', $path));

        $violationException = $thrown->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $violationException);

        $violation = $violationException->getViolations()->get(0);
        static::assertSame(ValidSeoPathInfo::INVALID_CHARACTERS, $violation->getCode());
        static::assertSame('/seoPathInfo', $violation->getPropertyPath());
    }

    private function dispatch(string $seoPathInfo): PreWriteValidationEvent
    {
        $command = new InsertCommand(
            $this->seoUrlDefinition,
            [
                'seo_path_info' => $seoPathInfo,
                'path_info' => '/detail/' . Uuid::randomHex(),
                'route_name' => 'frontend.detail.page',
                'foreign_key' => Uuid::randomBytes(),
                'language_id' => Uuid::randomBytes(),
            ],
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0/'
        );

        $event = new PreWriteValidationEvent($this->context, [$command]);
        (new SeoUrlWriteValidator())->preValidate($event);

        return $event;
    }
}
