<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class Migration1596441551CustomerGroupRegistrationTest extends TestCase
{
    use KernelTestBehaviour;

    public function testTablesArePresent(): void
    {
        $customerGroupColumns = array_column($this->getContainer()->get(Connection::class)->fetchAll('SHOW COLUMNS FROM customer_group'), 'Field');
        $customerGroupTranslationColumns = array_column($this->getContainer()->get(Connection::class)->fetchAll('SHOW COLUMNS FROM customer_group_translation'), 'Field');

        static::assertContains('registration_active', $customerGroupColumns);
        static::assertContains('registration_title', $customerGroupTranslationColumns);
        static::assertContains('registration_introduction', $customerGroupTranslationColumns);
        static::assertContains('registration_only_company_registration', $customerGroupTranslationColumns);
        static::assertContains('registration_seo_meta_description', $customerGroupTranslationColumns);
    }

    public function testMailTypesExists(): void
    {
        $typesCount = (int) $this->getContainer()->get(Connection::class)->fetchColumn('SELECT COUNT(*) FROM mail_template_type WHERE technical_name IN(\'customer.group.registration.accepted\', \'customer.group.registration.declined\')');
        static::assertSame(2, $typesCount);
    }

    public function testEventActionExists(): void
    {
        static::assertSame(1, (int) $this->getContainer()->get(Connection::class)->fetchColumn('SELECT COUNT(*) FROM event_action WHERE event_name = "customer.group.registration.accepted"'));
        static::assertSame(1, (int) $this->getContainer()->get(Connection::class)->fetchColumn('SELECT COUNT(*) FROM event_action WHERE event_name = "customer.group.registration.declined"'));
    }
}
