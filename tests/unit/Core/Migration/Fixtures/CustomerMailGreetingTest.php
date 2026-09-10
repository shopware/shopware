<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Migration\Fixtures;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Salutation\SalutationEntity;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 *
 * Renders the shipped templates rather than asserting on the entity, because a greeting can read
 * "Hello ," while every getter involved still returns what it promised.
 */
#[Package('checkout')]
#[CoversNothing]
class CustomerMailGreetingTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../../../src/Core/Migration/Fixtures/mails';

    private const TYPES = [
        'customer.group.registration.accepted',
        'customer.group.registration.declined',
        'customer.password.changed',
        'guest_order.double_opt_in',
        'password_change',
    ];

    private const FILES = [
        'en-plain.html.twig',
        'en-html.html.twig',
        'de-plain.html.twig',
        'de-html.html.twig',
    ];

    #[DataProvider('templateProvider')]
    public function testACompanyAccountWithoutAContactPersonIsGreetedByItsCompany(string $type, string $file): void
    {
        $rendered = $this->render($type, $file, $this->customer('Acme GmbH'));

        static::assertStringContainsString('Acme GmbH', $rendered);
        static::assertStringNotContainsString('  ', $this->greeting($rendered));
        static::assertStringNotContainsString(' ,', $this->greeting($rendered));
    }

    #[DataProvider('templateProvider')]
    public function testAContactPersonIsStillGreetedByName(string $type, string $file): void
    {
        $rendered = $this->render($type, $file, $this->customer('Ada Lovelace'));

        static::assertStringContainsString('Ada Lovelace', $rendered);
        static::assertStringNotContainsString('Acme GmbH', $rendered);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function templateProvider(): iterable
    {
        foreach (self::TYPES as $type) {
            foreach (self::FILES as $file) {
                yield $type . ' ' . $file => [$type, $file];
            }
        }
    }

    private function render(string $type, string $file, CustomerEntity $customer): string
    {
        $path = \sprintf('%s/%s/%s', self::FIXTURES, $type, $file);
        $template = file_get_contents($path);

        static::assertIsString($template, $path);

        $twig = new Environment(new ArrayLoader(['mail' => $template]));

        return $twig->render('mail', [
            'customer' => $customer,
            'shopName' => 'Demostore',
            'salesChannel' => ['translated' => ['name' => 'Demostore']],
            'customerGroup' => ['translated' => ['name' => 'Wholesale']],
            'resetUrl' => 'https://example.com/reset',
            'confirmUrl' => 'https://example.com/confirm',
        ]);
    }

    /**
     * Every shipped greeting is the first line that carries the name, and the assertions above are
     * about that line alone.
     */
    private function greeting(string $rendered): string
    {
        foreach (explode("\n", $rendered) as $line) {
            if (str_contains($line, 'Acme GmbH') || str_contains($line, 'Ada Lovelace')) {
                return trim(strip_tags($line));
            }
        }

        static::fail('the rendered mail carries no greeting');
    }

    private function customer(string $displayName): CustomerEntity
    {
        $salutation = new SalutationEntity();
        $salutation->setId('salutation-id');
        $salutation->setUniqueIdentifier('salutation-id');
        $salutation->setTranslated(['letterName' => 'Dear Sir or Madam', 'displayName' => 'Mr']);

        $customer = new CustomerEntity();
        $customer->setId('customer-id');
        $customer->setUniqueIdentifier('customer-id');
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_BUSINESS);
        $customer->setFirstName('');
        $customer->setLastName('');
        $customer->setCompany('Acme GmbH');
        $customer->setDisplayName($displayName);
        $customer->setSalutation($salutation);

        return $customer;
    }
}
