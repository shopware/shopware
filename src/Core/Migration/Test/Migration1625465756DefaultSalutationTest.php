<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1625465756DefaultSalutation as MigrationTested;
use Shopware\Core\System\Salutation\Aggregate\SalutationTranslation\SalutationTranslationEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

class Migration1625465756DefaultSalutationTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function setUp(): void
    {
        parent::setUp();

        $connection = $this->getContainer()->get(Connection::class);

        (new MigrationTested())->update($connection);
    }

    public function testDefaultSalutationIsCreated(): void
    {
        $salutationRepository = $this->getContainer()->get('salutation.repository');

        /** @var SalutationEntity $defaultSalutation */
        $defaultSalutation = $salutationRepository->search(
            (new Criteria([Defaults::SALUTATION]))->addAssociation('translation'),
            Context::createDefaultContext()
        )->first();

        static::assertInstanceOf(SalutationEntity::class, $defaultSalutation);
        static::assertEquals(MigrationTested::SALUTATION_KEY, $defaultSalutation->getSalutationKey());
    }

    public function testDefaultSalutationTranslationsAreCreated(): void
    {
        $translationRepository = $this->getContainer()->get('salutation_translation.repository');

        /** @var array<SalutationTranslationEntity> $translations */
        $translations = $translationRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('salutationId', Defaults::SALUTATION)),
            Context::createDefaultContext()
        )->getElements();

        static::assertCount(2, $translations);

        foreach ($translations as $translation) {
            static::assertInstanceOf(SalutationTranslationEntity::class, $translation);
            static::assertEmpty($translation->getLetterName());

            if ($translation->getLanguageId() === $this->getDeDeLanguageId()) {
                static::assertEquals(MigrationTested::SALUTATION_DISPLAY_NAME_DE, $translation->getDisplayName());
            } else {
                static::assertEquals(MigrationTested::SALUTATION_DISPLAY_NAME_EN, $translation->getDisplayName());
            }
        }
    }
}
