import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
    MAX_LISTED_FILES,
    collectPaths,
    detectDocsOnly,
    isMarkdownOnly,
    shouldCheck,
} from './markdown-only-changes.mjs';

function baseContext() {
    return {
        eventName: 'pull_request',
        repo: { owner: 'shopware', repo: 'shopware' },
        payload: { pull_request: { number: 42 } },
    };
}

function fakeCore() {
    const outputs = {};
    const warnings = [];

    return {
        outputs,
        warnings,
        setOutput: (name, value) => {
            outputs[name] = value;
        },
        warning: (message) => {
            warnings.push(message);
        },
    };
}

function fakeGithub(files) {
    return {
        rest: { pulls: { listFiles: Symbol('listFiles') } },
        paginate: async (endpoint, params) => {
            assert.equal(params.pull_number, 42);
            assert.equal(params.per_page, 100);

            if (files instanceof Error) {
                throw files;
            }

            return files;
        },
    };
}

describe('shouldCheck', () => {
    it('accepts pull_request events with a pull request number', () => {
        assert.equal(shouldCheck(baseContext()), true);
    });

    it('rejects other events', () => {
        for (const eventName of ['merge_group', 'push', 'workflow_dispatch']) {
            assert.equal(shouldCheck({ ...baseContext(), eventName }), false);
        }
    });

    it('rejects payloads without a pull request number', () => {
        assert.equal(shouldCheck({ eventName: 'pull_request', payload: {} }), false);
        assert.equal(shouldCheck({ eventName: 'pull_request', payload: { pull_request: {} } }), false);
    });
});

describe('isMarkdownOnly', () => {
    it('rejects an empty change set', () => {
        assert.equal(isMarkdownOnly([]), false);
    });

    it('accepts markdown-only paths case-insensitively', () => {
        assert.equal(isMarkdownOnly(['README.md', 'adr/2026-01-01-decision.MD']), true);
    });

    it('rejects mixed change sets', () => {
        assert.equal(isMarkdownOnly(['README.md', 'src/Core/Kernel.php']), false);
    });

    it('rejects paths where .md is not the extension', () => {
        assert.equal(isMarkdownOnly(['README.md.bak']), false);
        assert.equal(isMarkdownOnly(['docs/md']), false);
    });
});

describe('collectPaths', () => {
    it('includes the previous path of renamed files', () => {
        const files = [
            { filename: 'docs/README.md', previous_filename: 'README.md' },
            { filename: 'UPGRADE-6.8.md' },
        ];

        assert.deepEqual(collectPaths(files), ['docs/README.md', 'README.md', 'UPGRADE-6.8.md']);
    });
});

describe('detectDocsOnly', () => {
    it('reports true for a markdown-only pull request', async () => {
        const core = fakeCore();
        const result = await detectDocsOnly({
            github: fakeGithub([{ filename: 'README.md' }, { filename: 'coding-guidelines/core/README.md' }]),
            core,
            context: baseContext(),
        });

        assert.equal(result, true);
        assert.equal(core.outputs.docs_only, 'true');
    });

    it('reports false when any non-markdown file changed', async () => {
        const core = fakeCore();
        const result = await detectDocsOnly({
            github: fakeGithub([{ filename: 'README.md' }, { filename: 'composer.json' }]),
            core,
            context: baseContext(),
        });

        assert.equal(result, false);
        assert.equal(core.outputs.docs_only, 'false');
    });

    it('reports false when a markdown file was renamed from a non-markdown path', async () => {
        const core = fakeCore();
        const result = await detectDocsOnly({
            github: fakeGithub([{ filename: 'notes.md', previous_filename: 'notes.php' }]),
            core,
            context: baseContext(),
        });

        assert.equal(result, false);
        assert.equal(core.outputs.docs_only, 'false');
    });

    it('reports false for non pull_request events without calling the API', async () => {
        const core = fakeCore();
        const result = await detectDocsOnly({
            github: {
                rest: { pulls: {} },
                paginate: async () => {
                    throw new Error('must not be called');
                },
            },
            core,
            context: { ...baseContext(), eventName: 'merge_group' },
        });

        assert.equal(result, false);
        assert.equal(core.outputs.docs_only, 'false');
    });

    it('reports false when the file listing may be truncated', async () => {
        const core = fakeCore();
        const files = Array.from({ length: MAX_LISTED_FILES }, (unused, index) => ({ filename: `docs/${index}.md` }));
        const result = await detectDocsOnly({
            github: fakeGithub(files),
            core,
            context: baseContext(),
        });

        assert.equal(result, false);
        assert.equal(core.outputs.docs_only, 'false');
    });

    it('reports false and warns when the API call fails', async () => {
        const core = fakeCore();
        const result = await detectDocsOnly({
            github: fakeGithub(new Error('rate limited')),
            core,
            context: baseContext(),
        });

        assert.equal(result, false);
        assert.equal(core.outputs.docs_only, 'false');
        assert.equal(core.warnings.length, 1);
        assert.match(core.warnings[0], /rate limited/);
    });
});
