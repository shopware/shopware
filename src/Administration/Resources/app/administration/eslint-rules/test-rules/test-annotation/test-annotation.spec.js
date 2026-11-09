const { execFileSync } = require('node:child_process');
const { mkdtempSync, mkdirSync, writeFileSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { join } = require('node:path');
const { Linter, RuleTester } = require('eslint');
const rule = require('./index');
const seeExceptionRule = require('./see-exception');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const root = mkdtempSync(join(tmpdir(), 'test-annotation-'));
const admin = join(root, 'admin');
mkdirSync(join(root, 'adr'));
writeFileSync(join(root, 'adr/2026-01-01-decision.md'), '');
mkdirSync(join(admin, 'src/moved'), { recursive: true });
writeFileSync(
    join(admin, 'src/old.spec.js'),
    `describe('sw-old', () => {
    it('should render', () => {});
});
`,
);

// The base commit holds the untagged tests that may stay untagged
const git = (...args) => execFileSync('git', args, { cwd: root, stdio: 'ignore' });
git('init', '--quiet');
git('add', '.');
git(
    '-c',
    'user.name=test',
    '-c',
    'user.email=test@example.com',
    '-c',
    'commit.gpgsign=false',
    'commit',
    '--quiet',
    '-m',
    'base',
);
process.env.TEST_ANNOTATION_BASE = 'HEAD';

const options = [{ repoRoot: root, adminRoot: admin }];
const oldSpec = join(admin, 'src/old.spec.js');
const movedSpec = join(admin, 'src/moved/old.spec.js');
const ISSUE = 'https://github.com/shopware/shopware/issues/123';
const COMMENT = 'https://github.com/shopware/shopware/issues/123#issuecomment-456';

ruleTester.run('test-annotation', rule, {
    valid: [
        {
            name: 'accepts a characterization without a source',
            code: `describe('sw-foo', () => {
    /** @characterization */
    it('should render', () => {});
});`,
            options,
        },
        {
            name: 'accepts a regression that links its bug',
            code: `describe('sw-foo', () => {
    /**
     * Rows got lost after paging.
     * @regression
     * @see ${ISSUE}
     */
    it('should keep rows', () => {});
});`,
            options,
        },
        {
            name: 'accepts a behavior that links a comment, a discussion or an existing ADR',
            code: `describe('sw-foo', () => {
    /**
     * @behavior
     * @see ${COMMENT}
     */
    it('a', () => {});
    /**
     * @behavior
     * @see https://github.com/shopware/shopware/discussions/9#discussioncomment-10
     */
    it('b', () => {});
    /**
     * @behavior
     * @see adr/2026-01-01-decision.md
     */
    it('c', () => {});
});`,
            options,
        },
        {
            name: 'inherits the source from the nearest describe with the same kind',
            code: `describe('sw-foo', () => {
    /**
     * Rows got lost after paging.
     * @regression
     * @see ${ISSUE}
     */
    describe('paging', () => {
        /** @regression */
        it('should keep rows on the next page', () => {});
        /** @characterization */
        it('should render the badge', () => {});
    });
});`,
            options,
        },
        {
            name: 'recognizes the custom it helpers and it.each',
            code: `describe('sw-foo', () => {
    /** @characterization */
    it.each([1])('renders %s', () => {});
    /** @characterization */
    it.activeFeatureFlags(['V6_8_0_0'])('flagged', () => {});
    /** @characterization */
    it.deprecated('v6.8.0.0')('deprecated', () => {});
    it.todo('nothing to annotate yet');
});`,
            options,
        },
        {
            name: 'skips tests that were untagged at the base',
            code: `describe('sw-old', () => {
    it('should render', () => {});
});`,
            filename: oldSpec,
            options,
        },
        {
            name: 'keeps an untagged test that moved to another spec',
            code: `describe('sw-old', () => {
    it('should render', () => {});
});`,
            filename: movedSpec,
            options,
        },
        {
            name: 'accepts any source with a reasoned @seeException',
            code: `describe('sw-foo', () => {
    /**
     * @behavior
     * @see https://example.com/a11y#toasts
     * @seeException The accessibility guideline is the decision, there is no GitHub thread.
     */
    it('keeps the toast open on hover', () => {});
});`,
            options,
        },
    ],
    invalid: [
        {
            name: 'requires a kind on a new test',
            code: `describe('sw-foo', () => {
    it('should render', () => {});
});`,
            options,
            errors: [{ messageId: 'missingKind' }],
        },
        {
            name: 'a test at the base covers only as many copies in its file as it had',
            code: `describe('sw-old', () => {
    it('should render', () => {});
    it('should render', () => {});
});`,
            filename: oldSpec,
            options,
            errors: [{ messageId: 'missingKind', line: 3 }],
        },
        {
            name: 'a renamed test is new',
            code: `describe('sw-old', () => {
    it('should render the header', () => {});
});`,
            filename: oldSpec,
            options,
            errors: [{ messageId: 'missingKind' }],
        },
        {
            name: 'rejects two kinds',
            code: `describe('sw-foo', () => {
    /**
     * @regression
     * @characterization
     * @see ${ISSUE}
     */
    it('should render', () => {});
});`,
            options,
            errors: [{ messageId: 'multipleKinds' }],
        },
        {
            name: 'does not inherit the kind from a describe',
            code: `/**
 * @regression
 * @see ${ISSUE}
 */
describe('sw-foo', () => {
    it('should keep rows', () => {});
});`,
            options,
            errors: [{ messageId: 'missingKind' }],
        },
        {
            name: 'rejects @characterization on a describe',
            code: `/** @characterization */
describe('sw-foo', () => {
    /** @characterization */
    it('should render', () => {});
});`,
            options,
            errors: [{ messageId: 'characterizationOnDescribe' }],
        },
        {
            name: 'requires a source for a regression',
            code: `describe('sw-foo', () => {
    /** @regression */
    it('should keep rows', () => {});
});`,
            options,
            errors: [{ messageId: 'missingSee' }],
        },
        {
            name: 'requires a source on a tagged describe',
            code: `/** @behavior */
describe('sw-foo', () => {
    /** @behavior */
    it('should keep rows', () => {});
});`,
            options,
            errors: [{ messageId: 'missingSee' }, { messageId: 'missingSee' }],
        },
        {
            name: 'does not inherit a source of another kind',
            code: `/**
 * @regression
 * @see ${ISSUE}
 */
describe('sw-foo', () => {
    /** @behavior */
    it('should keep rows', () => {});
});`,
            options,
            errors: [{ messageId: 'inheritedKindMismatch' }],
        },
        {
            name: 'rejects an issue without a comment anchor as a behavior source',
            code: `describe('sw-foo', () => {
    /**
     * @behavior
     * @see ${ISSUE}
     */
    it('should keep rows', () => {});
});`,
            options,
            errors: [{ messageId: 'invalidSee' }],
        },
        {
            name: 'rejects a missing ADR',
            code: `describe('sw-foo', () => {
    /**
     * @behavior
     * @see adr/2026-01-01-missing.md
     */
    it('should keep rows', () => {});
});`,
            options,
            errors: [{ messageId: 'invalidSee' }],
        },
        {
            name: 'rejects a comment link as a regression source of another organization',
            code: `describe('sw-foo', () => {
    /**
     * @regression
     * @see https://github.com/someone/fork/issues/1
     */
    it('should keep rows', () => {});
});`,
            options,
            errors: [{ messageId: 'invalidSee' }],
        },
        {
            name: 'rejects a short @seeException reason',
            code: `describe('sw-foo', () => {
    /**
     * @behavior
     * @see https://example.com
     * @seeException ok
     */
    it('should keep rows', () => {});
});`,
            options,
            errors: [{ messageId: 'shortException' }],
        },
    ],
});

ruleTester.run('test-annotation-see-exception', seeExceptionRule, {
    valid: [
        {
            name: 'stays quiet without an exception',
            code: `describe('sw-foo', () => {
    /** @characterization */
    it('should render', () => {});
});`,
        },
    ],
    invalid: [
        {
            name: 'reports every exception',
            code: `describe('sw-foo', () => {
    /**
     * @behavior
     * @see https://example.com/a11y#toasts
     * @seeException The accessibility guideline is the decision.
     */
    it('keeps the toast open on hover', () => {});
});`,
            errors: [{ messageId: 'seeException' }],
        },
    ],
});

describe('without a base', () => {
    const untagged = `describe('sw-foo', () => {
    it('should render', () => {});
    it('should save', () => {});
});`;

    // A fresh rule per case, because it reports a missing base once per process
    const lint = (ruleOptions) => {
        let fresh;
        jest.isolateModules(() => {
            fresh = require('./index');
        });
        return new Linter({ configType: 'flat' }).verify(untagged, {
            plugins: { sw: { rules: { 'test-annotation': fresh } } },
            rules: { 'sw/test-annotation': ['error', ruleOptions] },
        });
    };

    afterEach(() => {
        process.env.TEST_ANNOTATION_BASE = 'HEAD';
    });

    it('explains a missing merge base once and lets untagged tests through', () => {
        process.env.TEST_ANNOTATION_BASE = 'does-not-exist';

        const messages = lint(options[0]);

        expect(messages).toHaveLength(1);
        expect(messages[0]).toMatchObject({ line: 1, message: expect.stringContaining('no merge base') });
    });

    it('explains a repository git cannot find', () => {
        const outside = mkdtempSync(join(tmpdir(), 'test-annotation-no-git-'));

        const messages = lint({ repoRoot: outside, adminRoot: outside });

        expect(messages).toHaveLength(1);
        expect(messages[0].message).toContain('git worktree repair --relative-paths');
    });
});
