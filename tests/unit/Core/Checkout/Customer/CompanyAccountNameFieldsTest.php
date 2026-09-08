<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CompanyAccountNameFields;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CompanyAccountNameFields::class)]
class CompanyAccountNameFieldsTest extends TestCase
{
    #[DataProvider('configProvider')]
    public function testAreRequired(?bool $show, ?bool $required, bool $expected): void
    {
        static::assertSame($expected, CompanyAccountNameFields::areRequired(
            $this->config($show, $required),
            TestDefaults::SALES_CHANNEL
        ));
    }

    public function testAreVisibleOnlyFollowsTheShowFlag(): void
    {
        static::assertTrue(CompanyAccountNameFields::areVisible($this->config(true, false), TestDefaults::SALES_CHANNEL));
        static::assertFalse(CompanyAccountNameFields::areVisible($this->config(false, true), TestDefaults::SALES_CHANNEL));
    }

    public function testAbsentKeysDefaultToRequired(): void
    {
        $config = new StaticSystemConfigService([TestDefaults::SALES_CHANNEL => []]);

        static::assertTrue(CompanyAccountNameFields::areRequired($config, TestDefaults::SALES_CHANNEL));
        static::assertTrue(CompanyAccountNameFields::areVisible($config, TestDefaults::SALES_CHANNEL));
    }

    public function testNormalizeFillsMissingNames(): void
    {
        $data = new DataBag(['company' => 'Acme GmbH']);

        CompanyAccountNameFields::normalize($data);

        static::assertSame('', $data->get('firstName'));
        static::assertSame('', $data->get('lastName'));
    }

    public function testNormalizeSubmittedOnlyRewritesKeysTheRequestSent(): void
    {
        $data = new DataBag(['firstName' => null]);

        CompanyAccountNameFields::normalizeSubmitted($data);

        static::assertSame('', $data->get('firstName'));
        static::assertFalse($data->has('lastName'));
    }

    public function testMakeOptionalKeepsEveryConstraintButNotBlank(): void
    {
        $length = new Length(max: 10);
        $regex = new Regex(pattern: '/^[a-z]+$/');

        $validation = new DataValidationDefinition('test');
        $validation->add('firstName', new NotBlank(), $length, $regex);
        $validation->add('lastName', new NotBlank(), $length);

        CompanyAccountNameFields::makeOptional($validation);

        static::assertSame([$length, $regex], $validation->getProperty('firstName'));
        static::assertSame([$length], $validation->getProperty('lastName'));
    }

    public function testMakeOptionalLeavesUntouchedPropertiesAlone(): void
    {
        $validation = new DataValidationDefinition('test');

        CompanyAccountNameFields::makeOptional($validation);

        static::assertSame([], $validation->getProperties());
    }

    public function testCompanyNotBlankRejectsWhitespace(): void
    {
        $normalizer = CompanyAccountNameFields::companyNotBlank()->normalizer;

        static::assertIsCallable($normalizer);
        static::assertSame('', $normalizer('   '));
        static::assertNull($normalizer(null));
    }

    /**
     * @return iterable<string, array{bool|null, bool|null, bool}>
     */
    public static function configProvider(): iterable
    {
        yield 'shown and required' => [true, true, true];
        yield 'shown but optional' => [true, false, false];
        yield 'hidden cannot be required' => [false, true, false];
        yield 'hidden and optional' => [false, false, false];
        yield 'never saved keeps the names required' => [null, null, true];
        yield 'only the required flag saved' => [null, false, false];
        yield 'only the show flag saved' => [false, null, false];
    }

    private function config(?bool $show, ?bool $required): StaticSystemConfigService
    {
        return new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                CompanyAccountNameFields::CONFIG_SHOW => $show,
                CompanyAccountNameFields::CONFIG_REQUIRED => $required,
            ],
        ]);
    }
}
