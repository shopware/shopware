<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Language;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Validation\ValidatorRegistryInterface;
use Shopware\Core\System\Language\LanguageValidator;

class LanguageValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var BatchValidatorInterface
     */
    protected $validator;

    /**
     * @var Context
     */
    private $defaultContext;

    /**
     * @var RepositoryInterface
     */
    private $languageRepository;

    /**
     * @var ValidatorRegistryInterface
     */
    private $validatorRegistry;

    public function setUp()
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
     * Add child
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
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'new no parent',
        ];
        $this->assertInsertViolations([$nid], []);
    }

    public function testUpsertSimple(): void
    {
        $nid = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'new no parent',
        ];

        $this->assertUpsertViolations([$nid], []);
    }

    public function testInsertWithParent(): void
    {
        $parent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'parent',
        ];
        $nidpid = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'new with parent id',
            'parentId' => $parent['id'],
        ];

        $this->assertInsertViolations([$parent, $nidpid], []);
    }

    public function testInsertWithEmbeddedParent(): void
    {
        // +a(+> +b)

        $a = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
            'parent' => [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'b',
            ],
        ];
        $this->assertInsertViolations([$a], []);
    }

    public function testUpsertWithEmbeddedParentId(): void
    {
        // +a(+> b)

        $b = ['id' => Uuid::uuid4()->getHex(), 'name' => 'a'];
        $this->languageRepository->create([$b], $this->defaultContext);

        $a = [
            'id' => Uuid::uuid4()->getHex(),
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

        $c = ['id' => Uuid::uuid4()->getHex(), 'name' => 'c'];
        $a = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
            'parent' => [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'b',
                'parentId' => $c['id'],
            ],
        ];
        $this->assertInsertViolations([$c, $a], [
            [LanguageValidator::PARENT_HAS_PARENT_VIOLATION, '/' . $a['id'] . '/parentId'],
        ]);
    }

    public function testUpsertWithEmbeddedParentIdThatHasAParentViolation(): void
    {
        // +a(+> b > c)

        $c = ['id' => Uuid::uuid4()->getHex(), 'name' => 'c'];
        $b = ['id' => Uuid::uuid4()->getHex(), 'name' => 'a', 'parentId' => $c['id']];
        $this->languageRepository->create([$c, $b], $this->defaultContext);

        $a = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
            'parent' => [
                'id' => $b['id'],
            ],
        ];
        $this->assertUpsertViolations([$a], [
            [LanguageValidator::PARENT_HAS_PARENT_VIOLATION, '/' . $a['id'] . '/parentId'],
        ]);
    }

    public function testUpsertWithParent(): void
    {
        $parent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'parent',
        ];
        $nidpid = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'new with parent id',
            'parentId' => $parent['id'],
        ];

        $this->assertUpsertViolations([$parent, $nidpid], []);
    }

    public function testInsertWithTwoChildren(): void
    {
        $parent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'parent',
        ];
        $child1 = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'child 1',
            'parentId' => $parent['id'],
        ];
        $child2 = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'child 2',
            'parentId' => $parent['id'],
        ];

        $this->assertInsertViolations([$parent, $child1, $child2], []);
    }

    public function testUpsertWithTwoChildren(): void
    {
        $parent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'parent',
        ];
        $child1 = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'child 1',
            'parentId' => $parent['id'],
        ];
        $child2 = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'child 2',
            'parentId' => $parent['id'],
        ];

        $this->assertUpsertViolations([$parent, $child1, $child2], []);
    }

    public function testInsertWithExistingParent(): void
    {
        $parent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'parent',
        ];
        $this->languageRepository->create([$parent], $this->defaultContext);

        $nidpid = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'new with parent id',
            'parentId' => $parent['id'],
        ];

        $this->assertInsertViolations([$nidpid], []);
    }

    public function testUpsertWithExistingParent(): void
    {
        $parent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'parent',
        ];
        $this->languageRepository->create([$parent], $this->defaultContext);

        $nidpid = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'new with parent id',
            'parentId' => $parent['id'],
        ];

        $this->assertUpsertViolations([$nidpid], []);
    }

    public function testUpdateSetParent(): void
    {
        // *a +> b

        $parent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'b',
        ];
        $toBeChild = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
        ];
        $this->languageRepository->create([$toBeChild, $parent], $this->defaultContext);

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
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'b',
        ];
        $parent2 = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'c',
        ];
        $toBeChild = [
            'id' => Uuid::uuid4()->getHex(),
            'parentId' => $parent['id'],
            'name' => 'a',
        ];
        $this->languageRepository->create([$parent,  $parent2, $toBeChild], $this->defaultContext);

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
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'c',
        ];

        $child1 = [
            'id' => Uuid::uuid4()->getHex(),
            'parentId' => $parent['id'],
            'name' => 'a',
        ];

        $child2 = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'b',
        ];

        $this->languageRepository->create([$parent, $child1, $child2], $this->defaultContext);

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
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'b',
        ];
        $child = [
            'id' => Uuid::uuid4()->getHex(),
            'parentId' => $parent['id'],
            'name' => 'a',
        ];
        $lang = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'c',
        ];
        $this->languageRepository->create([$parent, $child, $lang], $this->defaultContext);

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
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'c',
        ];

        $parent = [
            'id' => Uuid::uuid4()->getHex(),
            'parentId' => $parentParent['id'],
            'name' => 'b',
        ];

        $child = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
        ];

        $this->languageRepository->create([$parentParent, $parent, $child], $this->defaultContext);

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
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'c',
        ];
        $this->languageRepository->create([$parentParent], $this->defaultContext);

        $parent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'b',
            'parentId' => $parentParent['id'],
        ];
        $child = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
            'parentId' => $parent['id'],
        ];

        $this->assertInsertViolations([$parent, $child], [
            [LanguageValidator::PARENT_HAS_PARENT_VIOLATION, '/' . $child['id'] . '/parentId'],
        ]);
    }

    public function testInsertWithParentParentViolation(): void
    {
        $parentParent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'parent parent',
        ];
        $parent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'parent',
            'parentId' => $parentParent['id'],
        ];
        $child = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'child',
            'parentId' => $parent['id'],
        ];

        $this->assertInsertViolations([$parentParent, $parent, $child], [
            [LanguageValidator::PARENT_HAS_PARENT_VIOLATION, '/' . $child['id'] . '/parentId'],
        ]);
    }

    public function testInsertNonLocalParentWithParentViolation(): void
    {
        // +a +> b > c
        $c = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'c',
        ];
        $b = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'b',
            'parentId' => $c['id'],
        ];
        $this->languageRepository->create([$c, $b], $this->defaultContext);

        $a = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
            'parentId' => $b['id'],
        ];

        $this->assertInsertViolations([$a], [
            [LanguageValidator::PARENT_HAS_PARENT_VIOLATION, '/' . $a['id'] . '/parentId'],
        ]);
    }

    public function testUpdateSetParentOfParentViolation(): void
    {
        // a > *b +> c

        $parent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'b',
        ];
        $child = [
            'id' => Uuid::uuid4()->getHex(),
            'parentId' => $parent['id'],
            'name' => 'a',
        ];
        $lang = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'c',
        ];
        $this->languageRepository->create([$parent, $child, $lang], $this->defaultContext);

        $upd = [
            'id' => $parent['id'],
            'parentId' => $lang['id'],
        ];

        $this->assertUpdateViolations([$upd], [
            [LanguageValidator::PARENT_HAS_PARENT_VIOLATION, '/' . $child['id'] . '/parentId'],
        ]);
    }

    public function testUpdateSetParentToParentWithParentViolation(): void
    {
        // *a +> b > c
        // id = _a_[new_parent_id] AND parentId is not null

        $parentParent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'c',
        ];

        $parent = [
            'id' => Uuid::uuid4()->getHex(),
            'parentId' => $parentParent['id'],
            'name' => 'b',
        ];

        $wannabeChild = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
        ];

        $this->languageRepository->create([$parentParent, $parent, $wannabeChild], $this->defaultContext);

        $upd = [
            'id' => $wannabeChild['id'],
            'parentId' => $parent['id'],
        ];

        $this->assertUpdateViolations([$upd], [
            [LanguageValidator::PARENT_HAS_PARENT_VIOLATION, '/' . $upd['id'] . '/parentId'],
        ]);
    }

    public function testUpsertChangeParentToNewParent(): void
    {
        // a > b
        // *a *> +c

        $oldParent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'b',
        ];
        $child = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
            'parent_id' => $oldParent['id'],
        ];

        $existingData = [$oldParent, $child];

        $this->languageRepository->create($existingData, $this->defaultContext);

        $newParent = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'c',
        ];
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
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'c',
        ];
        $b = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'b',
            'parentId' => $c['id'],
        ];

        $this->languageRepository->create([$c, $b], $this->defaultContext);

        $a = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
            'parent_id' => $b,
        ];
        $bUpdate = [
            'id' => $b['id'],
            'parentId' => null,
        ];

        $this->assertUpsertViolations([$bUpdate, $a], []);
    }

    public function testRemoveChildAndAddNewParent(): void
    {
        // *a -> *b +> +c
        $b = ['id' => Uuid::uuid4()->getHex(), 'name' => 'b'];
        $a = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
            'parentId' => $b['id'],
        ];
        $this->languageRepository->create([$b, $a], $this->defaultContext);

        $aUpdate = ['id' => $a['id'], 'parentId' => null];
        $c = ['id' => Uuid::uuid4()->getHex(), 'name' => 'c'];
        $bUpdate = ['id' => $b['id'], 'parentId' => $c['id']];
        $this->assertUpsertViolations([$aUpdate, $c, $bUpdate], []);
    }

    public function testAddParentToNewLanguageViolation(): void
    {
        // a > *b +> +c

        $b = ['id' => Uuid::uuid4()->getHex(), 'name' => 'b'];
        $a = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
            'parentId' => $b['id'],
        ];
        $this->languageRepository->create([$b, $a], $this->defaultContext);

        $c = ['id' => Uuid::uuid4()->getHex(), 'name' => 'c'];
        $bUpdate = ['id' => $b['id'], 'parentId' => $c['id']];

        $this->assertUpsertViolations([$c, $bUpdate], [
            [LanguageValidator::PARENT_HAS_PARENT_VIOLATION, '/' . $a['id'] . '/parentId'],
        ]);
    }

    public function testUpsertAddNewParentWithParentToExistingLanguage(): void
    {
        // *a +> +b +> c

        $a = ['id' => Uuid::uuid4()->getHex(), 'name' => 'a'];
        $c = ['id' => Uuid::uuid4()->getHex(), 'name' => 'c'];

        $this->languageRepository->create([$a, $c], $this->defaultContext);

        $b = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'b',
            'parentId' => $c['id'],
        ];
        $aUpdate = [
            'id' => $a['id'],
            'parentId' => $b['id'],
        ];
        $this->assertUpsertViolations([$b, $aUpdate], [
            [LanguageValidator::PARENT_HAS_PARENT_VIOLATION, '/' . $a['id'] . '/parentId'],
        ]);
    }

    public function testChangeParentToNewLanguageWithParent(): void
    {
        // a > d, c
        // *a *> +b +> c

        $d = ['id' => Uuid::uuid4()->getHex(), 'name' => 'a'];
        $a = ['id' => Uuid::uuid4()->getHex(), 'name' => 'a', 'parentId' => $d['id']];
        $c = ['id' => Uuid::uuid4()->getHex(), 'name' => 'a'];
        $this->languageRepository->create([$d, $a, $c], $this->defaultContext);

        $b = [
            'id' => Uuid::uuid4()->getHex(),
            'parentId' => $c['id'],
            'name' => 'b',
        ];
        $aUpdate = ['id' => $a['id'], 'parentId' => $b['id']];
        $this->assertUpsertViolations([$b, $aUpdate], [
            [LanguageValidator::PARENT_HAS_PARENT_VIOLATION, '/' . $aUpdate['id'] . '/parentId'],
        ]);
    }

    public function testDeleteLanguage(): void
    {
        // -a
        $a = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'a',
        ];

        $this->languageRepository->create([$a], $this->defaultContext);

        $this->assertDeleteViolations([$a], []);
    }

    public function testDeleteEnglishViolation(): void
    {
        // -en
        $enGb = ['id' => Defaults::LANGUAGE_EN];

        $this->assertDeleteViolations(
            [$enGb],
            [
                [LanguageValidator::DELETE_DEFAULT_LANGUAGE_VIOLATION, '/' . $enGb['id']],
            ]
        );
    }

    public function testDeleteGermanViolation(): void
    {
        // -de
        $deDe = ['id' => Defaults::LANGUAGE_DE];

        $this->assertDeleteViolations(
            [$deDe],
            [
                [LanguageValidator::DELETE_DEFAULT_LANGUAGE_VIOLATION, '/' . $deDe['id']],
            ]
        );
    }

    public function testMultipleInsertViolations(): void
    {
        // +a1 +> +b +> +c, +a2 +> b

        $c = ['id' => Uuid::uuid4()->getHex(), 'name' => 'c'];
        $b = ['id' => Uuid::uuid4()->getHex(), 'name' => 'b', 'parentId' => $c['id']];
        $a1 = ['id' => Uuid::uuid4()->getHex(), 'name' => 'a1', 'parentId' => $b['id']];
        $a2 = ['id' => Uuid::uuid4()->getHex(), 'name' => 'a2', 'parentId' => $b['id']];

        $this->assertInsertViolations([$c, $b, $a1, $a2],
            [
                [LanguageValidator::PARENT_HAS_PARENT_VIOLATION, '/' . $a1['id'] . '/parentId'],
                [LanguageValidator::PARENT_HAS_PARENT_VIOLATION, '/' . $a2['id'] . '/parentId'],
            ]
        );
    }

    public function testMultipleDeleteViolations(): void
    {
        // -en, -de
        $enGb = ['id' => Defaults::LANGUAGE_EN];
        $deDe = ['id' => Defaults::LANGUAGE_DE];

        $this->assertDeleteViolations(
            [$enGb, $deDe],
            [
                [LanguageValidator::DELETE_DEFAULT_LANGUAGE_VIOLATION, '/' . $enGb['id']],
                [LanguageValidator::DELETE_DEFAULT_LANGUAGE_VIOLATION, '/' . $deDe['id']],
            ]
        );
    }

    protected function assertWriteStackViolations(callable $function, array $expectedCodePathPairs): void
    {
        /** @var WriteStackException|null $stack */
        $stack = null;
        try {
            $function();
        } catch (WriteStackException $exception) {
            $stack = $exception;
        }
        if (!empty($expectedCodePathPairs)) {
            static::assertInstanceOf(WriteStackException::class, $stack);
        }

        $actualViolations = $stack ? \iterator_to_array($stack->getErrors()) : [];

        foreach ($actualViolations as $violation) {
            $actual = [$violation['code'], $violation['source']['pointer']];
            static::assertContains($actual, $expectedCodePathPairs, 'Not found in :' . var_export($expectedCodePathPairs, true));
        }

        static::assertCount(\count($expectedCodePathPairs), $actualViolations);
    }

    protected function assertUpdateViolations(array $updateData, array $expectedCodePathPairs): void
    {
        $this->assertWriteStackViolations(function () use ($updateData) {
            $this->languageRepository->update($updateData, $this->defaultContext);
        }, $expectedCodePathPairs);
    }

    protected function assertInsertViolations(array $insertData, array $expectedCodePathPairs): void
    {
        $this->assertWriteStackViolations(function () use ($insertData) {
            $this->languageRepository->create($insertData, $this->defaultContext);
        }, $expectedCodePathPairs);
    }

    protected function assertUpsertViolations(array $upsertData, array $expectedCodePathPairs): void
    {
        $this->assertWriteStackViolations(function () use ($upsertData) {
            $this->languageRepository->upsert($upsertData, $this->defaultContext);
        }, $expectedCodePathPairs);
    }

    protected function assertDeleteViolations(array $ids, array $expectedCodePathPairs): void
    {
        $this->assertWriteStackViolations(function () use ($ids) {
            $this->languageRepository->delete($ids, $this->defaultContext);
        }, $expectedCodePathPairs);
    }
}
