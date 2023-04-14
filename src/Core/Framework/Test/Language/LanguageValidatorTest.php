<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Language;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageValidator;

/**
 * @internal
 */
class LanguageValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $defaultContext;

    /**
     * @var EntityRepository
     */
    private $languageRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultContext = Context::createDefaultContext();
        $this->languageRepository = $this->getContainer()->get('language.repository');
    }

    /**
     * Overview of test cases
     *
     * Valid:
     *
     * +a +> +b
     * +a +> b
     * *a +> b
     * *a *> c
     *
     * Associate child
     * a > c
     * *b +> c
     * +d +> c
     *
     * updates
     * *a +> *b -> c
     * *a -> *b +> c
     *
     * upserts
     * *a *> +b
     * +a +> *b -> c
     * *a -> *b +> +c
     *
     * Invalid:
     *
     * inserts
     * +a +> +b +> +c
     * +a +> +b +> c
     * +a +> b > c
     *
     * updates
     * *a +> b > c
     * *a *> b > c
     * a > *b +> c
     *
     * upsert
     * a > *b +> +c
     * *a +> +b +> c
     * *a *> +b +> c
     *
     * Legend:
     * +a: Insert new language a
     * -a: Delete language a
     * *a: Update language a
     * a: Existing language a
     *
     * +>: New parent connection
     * *>: Change parent connection
     * ->: Remove parent connection
     * >: Existing connection
     *
     * Example:
     * *a *> +b +> c
     *
     * Change parent of existing language `a` to new language `b`
     * which adds a new parent connection to existing language `c`.
     */
    public function testInsertSimple(): void
    {
        $nid = [
            'id' => Uuid::randomHex(),
            'name' => 'new no parent',
        ];
        $this->assertInsertViolations([$nid], []);
    }

    public function testUpsertSimple(): void
    {
        $nid = [
            'id' => Uuid::randomHex(),
            'name' => 'new no parent',
        ];

        $this->assertUpsertViolations([$nid], []);
    }

    public function testInsertWithParent(): void
    {
        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'parent',
        ];
        $nidpid = [
            'id' => Uuid::randomHex(),
            'name' => 'new with parent id',
            'parentId' => $parent['id'],
        ];

        $this->assertInsertViolations([$parent, $nidpid], []);
    }

    public function testInsertWithEmbeddedParent(): void
    {
        // +a(+> +b)

        $a = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
            'parent' => [
                'id' => Uuid::randomHex(),
                'name' => 'b',
            ],
        ];
        $this->assertInsertViolations([$a], []);
    }

    public function testUpsertWithEmbeddedParentId(): void
    {
        // +a(+> b)

        $b = ['id' => Uuid::randomHex(), 'name' => 'a'];
        $this->addLanguagesWithDefaultLocales([$b]);

        $a = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
            'parent' => [
                'id' => $b['id'],
            ],
        ];
        $this->assertUpsertViolations([$a], []);
    }

    public function testInsertWithEmbeddedParentWithParentViolation(): void
    {
        // +a(+> +b +> c)

        $c = ['id' => Uuid::randomHex(), 'name' => 'c'];
        $a = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
            'parent' => [
                'id' => Uuid::randomHex(),
                'name' => 'b',
                'parentId' => $c['id'],
            ],
        ];
        $this->assertInsertViolations([$c, $a], [
            [LanguageValidator::VIOLATION_PARENT_HAS_PARENT, '/' . $a['id'] . '/parentId'],
        ]);
    }

    public function testUpsertWithEmbeddedParentIdThatHasAParentViolation(): void
    {
        // +a(+> b > c)

        $c = ['id' => Uuid::randomHex(), 'name' => 'c'];
        $b = ['id' => Uuid::randomHex(), 'name' => 'a', 'parentId' => $c['id']];
        $this->addLanguagesWithDefaultLocales([$c, $b]);

        $a = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
            'parent' => [
                'id' => $b['id'],
            ],
        ];
        $this->assertUpsertViolations([$a], [
            [LanguageValidator::VIOLATION_PARENT_HAS_PARENT, '/' . $a['id'] . '/parentId'],
        ]);
    }

    public function testUpsertWithParent(): void
    {
        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'parent',
        ];
        $nidpid = [
            'id' => Uuid::randomHex(),
            'name' => 'new with parent id',
            'parentId' => $parent['id'],
        ];

        $this->assertUpsertViolations([$parent, $nidpid], []);
    }

    public function testInsertWithTwoChildren(): void
    {
        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'parent',
        ];
        $child1 = [
            'id' => Uuid::randomHex(),
            'name' => 'child 1',
            'parentId' => $parent['id'],
        ];
        $child2 = [
            'id' => Uuid::randomHex(),
            'name' => 'child 2',
            'parentId' => $parent['id'],
        ];

        $this->assertInsertViolations([$parent, $child1, $child2], []);
    }

    public function testUpsertWithTwoChildren(): void
    {
        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'parent',
        ];
        $child1 = [
            'id' => Uuid::randomHex(),
            'name' => 'child 1',
            'parentId' => $parent['id'],
        ];
        $child2 = [
            'id' => Uuid::randomHex(),
            'name' => 'child 2',
            'parentId' => $parent['id'],
        ];

        $this->assertUpsertViolations([$parent, $child1, $child2], []);
    }

    public function testInsertWithExistingParent(): void
    {
        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'parent',
        ];
        $this->addLanguagesWithDefaultLocales([$parent]);

        $nidpid = [
            'id' => Uuid::randomHex(),
            'name' => 'new with parent id',
            'parentId' => $parent['id'],
        ];

        $this->assertInsertViolations([$nidpid], []);
    }

    public function testUpsertWithExistingParent(): void
    {
        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'parent',
        ];
        $this->addLanguagesWithDefaultLocales([$parent]);

        $nidpid = [
            'id' => Uuid::randomHex(),
            'name' => 'new with parent id',
            'parentId' => $parent['id'],
        ];

        $this->assertUpsertViolations([$nidpid], []);
    }

    public function testUpdateSetParent(): void
    {
        // *a +> b

        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'b',
        ];
        $toBeChild = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
        ];
        $this->addLanguagesWithDefaultLocales([$toBeChild, $parent]);

        $upd = [
            'id' => $toBeChild['id'],
            'parentId' => $parent['id'],
        ];

        $this->assertUpdateViolations([$upd], []);
        $this->assertUpsertViolations([$upd], []);
    }

    public function testUpdateChangeParent(): void
    {
        // *a *> b

        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'b',
        ];
        $parent2 = [
            'id' => Uuid::randomHex(),
            'name' => 'c',
        ];
        $toBeChild = [
            'id' => Uuid::randomHex(),
            'parentId' => $parent['id'],
            'name' => 'a',
        ];
        $this->addLanguagesWithDefaultLocales([$parent, $parent2, $toBeChild]);

        $upd = [
            'id' => $toBeChild['id'],
            'parentId' => $parent2['id'],
        ];

        $this->assertUpdateViolations([$upd], []);
        $this->assertUpsertViolations([$upd], []);
    }

    public function testUpdateAddChild(): void
    {
        // a  > c
        // *b +> c

        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'c',
        ];

        $child1 = [
            'id' => Uuid::randomHex(),
            'parentId' => $parent['id'],
            'name' => 'a',
        ];

        $child2 = [
            'id' => Uuid::randomHex(),
            'name' => 'b',
        ];

        $this->addLanguagesWithDefaultLocales([$parent, $child1, $child2]);

        $upd = [
            'id' => $child2['id'],
            'parentId' => $parent['id'],
        ];

        $this->assertUpdateViolations([$upd], []);
        $this->assertUpsertViolations([$upd], []);
    }

    public function testUpdateSetParentOfUnsetParent(): void
    {
        // *a -> *b +> c

        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'b',
        ];
        $child = [
            'id' => Uuid::randomHex(),
            'parentId' => $parent['id'],
            'name' => 'a',
        ];
        $lang = [
            'id' => Uuid::randomHex(),
            'name' => 'c',
        ];
        $this->addLanguagesWithDefaultLocales([$parent, $child, $lang]);

        $upd = [
            [
                'id' => $parent['id'],
                'parentId' => $lang['id'],
            ],
            [
                'id' => $child['id'],
                'parentId' => null,
            ],
        ];

        $this->assertUpdateViolations($upd, []);
        $this->assertUpsertViolations($upd, []);
    }

    public function testUpdateSetParentToParentWithUnsetParent(): void
    {
        // *a +> *b -> c

        $parentParent = [
            'id' => Uuid::randomHex(),
            'name' => 'c',
        ];

        $parent = [
            'id' => Uuid::randomHex(),
            'parentId' => $parentParent['id'],
            'name' => 'b',
        ];

        $child = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
        ];

        $this->addLanguagesWithDefaultLocales([$parentParent, $parent, $child]);

        $upd = [
            [
                'id' => $child['id'],
                'parentId' => $parent['id'],
            ],
            [
                'id' => $parent['id'],
                'parentId' => null,
            ],
        ];

        $this->assertUpdateViolations($upd, []);
        $this->assertUpsertViolations($upd, []);
    }

    public function testInsertWithNonLocalParentParentViolation(): void
    {
        // +a +> +b +> c

        $parentParent = [
            'id' => Uuid::randomHex(),
            'name' => 'c',
        ];
        $this->addLanguagesWithDefaultLocales([$parentParent]);

        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'b',
            'parentId' => $parentParent['id'],
        ];
        $child = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
            'parentId' => $parent['id'],
        ];

        $this->assertInsertViolations([$parent, $child], [
            [LanguageValidator::VIOLATION_PARENT_HAS_PARENT, '/' . $child['id'] . '/parentId'],
        ]);
    }

    public function testInsertWithParentParentViolation(): void
    {
        $parentParent = [
            'id' => Uuid::randomHex(),
            'name' => 'parent parent',
        ];
        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'parent',
            'parentId' => $parentParent['id'],
        ];
        $child = [
            'id' => Uuid::randomHex(),
            'name' => 'child',
            'parentId' => $parent['id'],
        ];

        $this->assertInsertViolations([$parentParent, $parent, $child], [
            [LanguageValidator::VIOLATION_PARENT_HAS_PARENT, '/' . $child['id'] . '/parentId'],
        ]);
    }

    public function testInsertNonLocalParentWithParentViolation(): void
    {
        // +a +> b > c
        $c = [
            'id' => Uuid::randomHex(),
            'name' => 'c',
        ];
        $b = [
            'id' => Uuid::randomHex(),
            'name' => 'b',
            'parentId' => $c['id'],
        ];
        $this->addLanguagesWithDefaultLocales([$c, $b]);

        $a = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
            'parentId' => $b['id'],
        ];

        $this->assertInsertViolations([$a], [
            [LanguageValidator::VIOLATION_PARENT_HAS_PARENT, '/' . $a['id'] . '/parentId'],
        ]);
    }

    public function testUpdateSetParentOfParentViolation(): void
    {
        // a > *b +> c

        $parent = [
            'id' => Uuid::randomHex(),
            'name' => 'b',
        ];
        $child = [
            'id' => Uuid::randomHex(),
            'parentId' => $parent['id'],
            'name' => 'a',
        ];
        $lang = [
            'id' => Uuid::randomHex(),
            'name' => 'c',
        ];
        $this->addLanguagesWithDefaultLocales([$parent, $child, $lang]);

        $upd = [
            'id' => $parent['id'],
            'parentId' => $lang['id'],
        ];

        $this->assertUpdateViolations([$upd], [
            [LanguageValidator::VIOLATION_PARENT_HAS_PARENT, '/' . $child['id'] . '/parentId'],
        ]);
    }

    public function testUpdateSetParentToParentWithParentViolation(): void
    {
        // *a +> b > c
        // id = _a_[new_parent_id] AND parentId is not null

        $parentParent = [
            'id' => Uuid::randomHex(),
            'name' => 'c',
        ];

        $parent = [
            'id' => Uuid::randomHex(),
            'parentId' => $parentParent['id'],
            'name' => 'b',
        ];

        $wannabeChild = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
        ];

        $this->addLanguagesWithDefaultLocales([$parentParent, $parent, $wannabeChild]);

        $upd = [
            'id' => $wannabeChild['id'],
            'parentId' => $parent['id'],
        ];

        $this->assertUpdateViolations([$upd], [
            [LanguageValidator::VIOLATION_PARENT_HAS_PARENT, '/' . $upd['id'] . '/parentId'],
        ]);
    }

    public function testUpsertChangeParentToNewParent(): void
    {
        // a > b
        // *a *> +c

        $oldParent = [
            'id' => Uuid::randomHex(),
            'name' => 'b',
        ];
        $child = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
            'parent_id' => $oldParent['id'],
        ];

        $existingData = [$oldParent, $child];

        $this->addLanguagesWithDefaultLocales($existingData);

        $newParent = $this->addDefaultLocale([
            'id' => Uuid::randomHex(),
            'name' => 'c',
        ]);
        $updChild = [
            'id' => $child['id'],
            'parentId' => $newParent['id'],
        ];

        $this->assertUpsertViolations([$newParent, $updChild], []);
    }

    public function testUpsertAddParentWithUnsetParent(): void
    {
        // +a +> *b -> c

        $c = [
            'id' => Uuid::randomHex(),
            'name' => 'c',
        ];
        $b = [
            'id' => Uuid::randomHex(),
            'name' => 'b',
            'parentId' => $c['id'],
        ];

        $this->addLanguagesWithDefaultLocales([$c, $b]);

        $a = $this->addDefaultLocale([
            'id' => Uuid::randomHex(),
            'name' => 'a',
            'parent_id' => $b,
        ]);
        $bUpdate = [
            'id' => $b['id'],
            'parentId' => null,
        ];

        $this->assertUpsertViolations([$bUpdate, $a], []);
    }

    public function testRemoveChildAndAddNewParent(): void
    {
        // *a -> *b +> +c
        $b = ['id' => Uuid::randomHex(), 'name' => 'b'];
        $a = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
            'parentId' => $b['id'],
        ];
        $this->addLanguagesWithDefaultLocales([$b, $a]);

        $aUpdate = ['id' => $a['id'], 'parentId' => null];
        $c = $this->addDefaultLocale(['id' => Uuid::randomHex(), 'name' => 'c']);
        $bUpdate = ['id' => $b['id'], 'parentId' => $c['id']];
        $this->assertUpsertViolations([$aUpdate, $c, $bUpdate], []);
    }

    public function testAddParentToNewLanguageViolation(): void
    {
        // a > *b +> +c

        $b = ['id' => Uuid::randomHex(), 'name' => 'b'];
        $a = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
            'parentId' => $b['id'],
        ];
        $this->addLanguagesWithDefaultLocales([$b, $a]);

        $c = $this->addDefaultLocale(['id' => Uuid::randomHex(), 'name' => 'c']);
        $bUpdate = ['id' => $b['id'], 'parentId' => $c['id']];

        $this->assertUpsertViolations([$c, $bUpdate], [
            [LanguageValidator::VIOLATION_PARENT_HAS_PARENT, '/' . $a['id'] . '/parentId'],
        ]);
    }

    public function testUpsertAddNewParentWithParentToExistingLanguage(): void
    {
        // *a +> +b +> c

        $a = ['id' => Uuid::randomHex(), 'name' => 'a'];
        $c = ['id' => Uuid::randomHex(), 'name' => 'c'];

        $this->addLanguagesWithDefaultLocales([$a, $c]);

        $b = [
            'id' => Uuid::randomHex(),
            'name' => 'b',
            'parentId' => $c['id'],
        ];
        $aUpdate = [
            'id' => $a['id'],
            'parentId' => $b['id'],
        ];
        $this->assertUpsertViolations([$b, $aUpdate], [
            [LanguageValidator::VIOLATION_PARENT_HAS_PARENT, '/' . $a['id'] . '/parentId'],
        ]);
    }

    public function testChangeParentToNewLanguageWithParent(): void
    {
        // a > d, c
        // *a *> +b +> c

        $d = ['id' => Uuid::randomHex(), 'name' => 'a'];
        $a = ['id' => Uuid::randomHex(), 'name' => 'a', 'parentId' => $d['id']];
        $c = ['id' => Uuid::randomHex(), 'name' => 'a'];
        $this->addLanguagesWithDefaultLocales([$d, $a, $c]);

        $b = [
            'id' => Uuid::randomHex(),
            'parentId' => $c['id'],
            'name' => 'b',
        ];
        $aUpdate = ['id' => $a['id'], 'parentId' => $b['id']];
        $this->assertUpsertViolations([$b, $aUpdate], [
            [LanguageValidator::VIOLATION_PARENT_HAS_PARENT, '/' . $aUpdate['id'] . '/parentId'],
        ]);
    }

    public function testDeleteLanguage(): void
    {
        // -a
        $a = [
            'id' => Uuid::randomHex(),
            'name' => 'a',
        ];

        $this->addLanguagesWithDefaultLocales([$a]);

        $this->assertDeleteViolations([$a], []);
    }

    public function testSetParentOfSystemDefaultViolation(): void
    {
        // *systemDefault +> UUID

        $systemDefaultLanguage = [
            'id' => Defaults::LANGUAGE_SYSTEM,
            'parentId' => Uuid::randomHex(),
        ];

        $this->assertUpsertViolations([$systemDefaultLanguage], [
            [LanguageValidator::VIOLATION_DEFAULT_LANGUAGE_PARENT, '/0/parentId'],
        ]);
    }

    public function testDeleteEnglishViolation(): void
    {
        // -en
        $enGb = ['id' => Defaults::LANGUAGE_SYSTEM];

        $this->assertDeleteViolations(
            [$enGb],
            [
                [LanguageValidator::VIOLATION_DELETE_DEFAULT_LANGUAGE, '/' . $enGb['id']],
            ]
        );
    }

    public function testMultipleInsertViolations(): void
    {
        // +a1 +> +b +> +c, +a2 +> b

        $c = ['id' => Uuid::randomHex(), 'name' => 'c'];
        $b = ['id' => Uuid::randomHex(), 'name' => 'b', 'parentId' => $c['id']];
        $a1 = ['id' => Uuid::randomHex(), 'name' => 'a1', 'parentId' => $b['id']];
        $a2 = ['id' => Uuid::randomHex(), 'name' => 'a2', 'parentId' => $b['id']];

        $this->assertInsertViolations(
            [$c, $b, $a1, $a2],
            [
                [LanguageValidator::VIOLATION_PARENT_HAS_PARENT, '/' . $a1['id'] . '/parentId'],
                [LanguageValidator::VIOLATION_PARENT_HAS_PARENT, '/' . $a2['id'] . '/parentId'],
            ]
        );
    }

    public function testRootWithoutTranslationCodeViolation(): void
    {
        $root = ['id' => Uuid::randomHex(), 'name' => 'root without language code'];

        $this->assertInsertViolations(
            [$root],
            [
                [LanguageValidator::VIOLATION_CODE_REQUIRED_FOR_ROOT_LANGUAGE, '/' . $root['id'] . '/translationCodeId'],
            ],
            false // no default locale !
        );
    }

    public function testSubWithoutTranslationCode(): void
    {
        $root = $this->addDefaultTranslationCode(['id' => Uuid::randomHex(), 'name' => 'root with language code']);
        $sub = ['id' => Uuid::randomHex(), 'name' => 'sub without language code', 'parentId' => $root['id']];
        $this->assertInsertViolations([$root, $sub], [], false /* no default locale ! */);
    }

    /**
     * @param callable(): void $function
     * @param list<list<string>> $expectedCodePathPairs
     */
    protected function assertWriteStackViolations(callable $function, array $expectedCodePathPairs): void
    {
        /** @var WriteException|null $stack */
        $stack = null;

        try {
            $function();
        } catch (WriteException $exception) {
            $stack = $exception;
        }
        if (!empty($expectedCodePathPairs)) {
            static::assertInstanceOf(WriteException::class, $stack);
        }

        $actualViolations = $stack ? iterator_to_array($stack->getErrors()) : [];

        foreach ($actualViolations as $violation) {
            $actual = [$violation['code'], $violation['source']['pointer']];
            static::assertContains($actual, $expectedCodePathPairs, 'Not found in :' . var_export($expectedCodePathPairs, true));
        }

        static::assertCount(\count($expectedCodePathPairs), $actualViolations);
    }

    /**
     * @param list<array<string, mixed>> $updateData
     * @param list<list<string>> $expectedCodePathPairs
     */
    protected function assertUpdateViolations(array $updateData, array $expectedCodePathPairs): void
    {
        $this->assertWriteStackViolations(function () use ($updateData): void {
            $this->languageRepository->update($updateData, $this->defaultContext);
        }, $expectedCodePathPairs);
    }

    /**
     * @param list<array<string, mixed>> $insertData
     * @param list<list<string>> $expectedCodePathPairs
     */
    protected function assertInsertViolations(array $insertData, array $expectedCodePathPairs, bool $addDefaultTranslationCode = true): void
    {
        if ($addDefaultTranslationCode) {
            $insertData = $this->addDefaultTranslationCodes($insertData);
        }
        $insertData = $this->addDefaultLocales($insertData);
        $this->assertWriteStackViolations(function () use ($insertData): void {
            $this->languageRepository->create($insertData, $this->defaultContext);
        }, $expectedCodePathPairs);
    }

    /**
     * @param list<array<string, mixed>> $upsertData
     * @param list<list<string>> $expectedCodePathPairs
     */
    protected function assertUpsertViolations(array $upsertData, array $expectedCodePathPairs, bool $addDefaultTranslationCode = true): void
    {
        if ($addDefaultTranslationCode) {
            $upsertData = $this->addDefaultTranslationCodes($upsertData);
        }
        $upsertData = $this->addDefaultLocales($upsertData);
        $this->assertWriteStackViolations(function () use ($upsertData): void {
            $this->languageRepository->upsert($upsertData, $this->defaultContext);
        }, $expectedCodePathPairs);
    }

    /**
     * @param array<array<string, mixed|null>> $ids
     * @param list<list<string>> $expectedCodePathPairs
     */
    protected function assertDeleteViolations(array $ids, array $expectedCodePathPairs): void
    {
        $this->assertWriteStackViolations(function () use ($ids): void {
            $this->languageRepository->delete($ids, $this->defaultContext);
        }, $expectedCodePathPairs);
    }

    protected function addLanguagesWithDefaultLocales(array $languages): void
    {
        $this->languageRepository->create($this->addDefaultTranslationCodes($this->addDefaultLocales($languages)), $this->defaultContext);
    }

    protected function addDefaultLocales(array $languages): array
    {
        return array_map(fn ($lang) => $this->addDefaultLocale($lang), $languages);
    }

    protected function addDefaultLocale(array $lang): array
    {
        if (!isset($lang['locale']) && !isset($lang['localeId'])) {
            $lang['localeId'] = $this->getLocaleIdOfSystemLanguage();
        }
        if (isset($lang['parent']) && !isset($lang['parent']['locale'], $lang['parent']['localeId'])) {
            $lang['parent']['localeId'] = $this->getLocaleIdOfSystemLanguage();
        }

        return $lang;
    }

    protected function addDefaultTranslationCodes(array $languages)
    {
        return array_map(fn ($lang) => $this->addDefaultTranslationCode($lang), $languages);
    }

    protected function addDefaultTranslationCode(array $lang)
    {
        if (!isset($lang['translationCode']) && !isset($lang['translationCodeId'])) {
            $id = Uuid::randomHex();
            $lang['translationCode'] = [
                'code' => 'x-tst_' . $id,
                'name' => 'test translation code ' . $id,
                'territory' => 'test translation territory ' . $id,
            ];
        }
        if (isset($lang['parent']) && !isset($lang['parent']['translationCode']) && !isset($lang['parent']['translationCodeId'])) {
            $id = Uuid::randomHex();
            $lang['parent']['translationCode'] = [
                'code' => 'x-tst_' . $id,
                'name' => 'test translation code parent ' . $id,
                'territory' => 'test translation territory ' . $id,
            ];
        }

        return $lang;
    }
}
