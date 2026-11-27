const { mkdtempSync, mkdirSync, rmSync, writeFileSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { join } = require('node:path');
const { RuleTester } = require('eslint');
const rule = require('./index');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const root = mkdtempSync(join(tmpdir(), 'test-annotation-'));
mkdirSync(join(root, 'adr'));
writeFileSync(join(root, 'adr/2026-01-01-decision.md'), '');
afterAll(() => rmSync(root, { recursive: true, force: true }));

const options = [{ repoRoot: root }];
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
    /**
     * @behavior
     * @see https://github.com/shopware/shopware/pull/123/files#r456
     */
    it('d', () => {});
    /**
     * @behavior
     * @see https://github.com/orgs/shopware/discussions/12
     */
    it('e', () => {});
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
            name: 'inherits through nested describes, skipping describes with another or no tag',
            code: `/**
 * @regression
 * @see ${ISSUE}
 */
describe('sw-foo', () => {
    /**
     * Shared helpers.
     * @see https://example.com/helpers
     */
    describe('helpers', () => {
        /** @regression */
        describe('paging', () => {
            /** @regression */
            it('should keep rows', () => {});
        });
    });
});`,
            options,
        },
        {
            name: 'a test with only a @seeException still inherits the @see',
            code: `/**
 * @regression
 * @see ${ISSUE}
 */
describe('sw-foo', () => {
    /**
     * @regression
     * @seeException The customer report in the support ticket shows the same bug.
     */
    it('should keep rows', () => {});
});`,
            options,
        },
        {
            name: 'reads the url of a JSDoc {@link}',
            code: `describe('sw-foo', () => {
    /**
     * @regression
     * @see {@link ${ISSUE}}
     */
    it('should keep rows', () => {});
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
            name: 'leaves untagged tests alone',
            code: `describe('sw-foo', () => {
    it('should render', () => {});
});`,
            options,
        },
        {
            name: 'does not inherit the kind from a describe, so the test counts as untagged',
            code: `/**
 * @regression
 * @see ${ISSUE}
 */
describe('sw-foo', () => {
    it('should keep rows', () => {});
});`,
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
            errors: [{ messageId: 'missingSee' }],
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
            name: 'rejects an issue of another organization as a regression source',
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
            name: 'reports several invalid sources once',
            code: `describe('sw-foo', () => {
    /**
     * @behavior
     * @see ${ISSUE}
     * @see https://github.com/shopware/shopware/issues/124
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
