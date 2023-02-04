<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContactForm\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContactForm\Validation\ContactFormValidationFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @package content
 *
 * @covers \Shopware\Core\Content\ContactForm\Validation\ContactFormValidationFactory
 */
class ContactFormValidationFactoryTest extends TestCase
{
    /**
     * @dataProvider systemConfigDataProvider
     */
    public function testCreate(bool $required, \Closure $expectsClosure): void
    {
        $systemConfigServiceMock = $this->createMock(SystemConfigService::class);
        $systemConfigServiceMock->method('get')->willReturn($required);

        $validation = new ContactFormValidationFactory(
            $this->createMock(EventDispatcherInterface::class),
            $systemConfigServiceMock
        );

        $contextMock = $this->createMock(SalesChannelContext::class);

        $definition = $validation->create($contextMock);

        $expectsClosure($definition, $contextMock);
    }

    public function systemConfigDataProvider(): \Generator
    {
        yield 'is required' => [
            true,
            function (DataValidationDefinition $definition, SalesChannelContext $context): void {
                static::assertEquals($definition->getProperties(), [
                    'salutationId' => [
                        new NotBlank(),
                        new EntityExists(['entity' => 'salutation', 'context' => $context->getContext()]),
                    ],
                    'email' => [new NotBlank(), new Email()],
                    'subject' => [new NotBlank()],
                    'comment' => [new NotBlank()],
                    'firstName' => [
                        new NotBlank(),
                        new Regex(['pattern' => ContactFormValidationFactory::DOMAIN_NAME_REGEX, 'match' => false]),
                    ],
                    'lastName' => [
                        new NotBlank(),
                        new Regex(['pattern' => ContactFormValidationFactory::DOMAIN_NAME_REGEX, 'match' => false]),
                    ],
                    'phone' => [new NotBlank()],
                ]);
            },
        ];

        yield 'is not required' => [
            false,
            function (DataValidationDefinition $definition, SalesChannelContext $context): void {
                static::assertEquals($definition->getProperties(), [
                    'salutationId' => [
                        new NotBlank(),
                        new EntityExists(['entity' => 'salutation', 'context' => $context->getContext()]),
                    ],
                    'email' => [new NotBlank(), new Email()],
                    'subject' => [new NotBlank()],
                    'comment' => [new NotBlank()],
                    'firstName' => [
                        new Regex(['pattern' => ContactFormValidationFactory::DOMAIN_NAME_REGEX, 'match' => false]),
                    ],
                    'lastName' => [
                        new Regex(['pattern' => ContactFormValidationFactory::DOMAIN_NAME_REGEX, 'match' => false]),
                    ],
                ]);
            },
        ];
    }
}
