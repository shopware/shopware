import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, test } from 'node:test';

import { MIN_LENGTH, checkPullRequestDescription, evaluateDescription } from './pull-request-description.ts';

const TEMPLATE_PATH = join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'PULL_REQUEST_TEMPLATE.md');
const template = readFileSync(TEMPLATE_PATH, 'utf8');

const filledTemplate = template
    .replace('### 1. What and why?', '### 1. What and why?\nThe cart total was wrong for bundles.')
    .replace('### 2. How?', '### 2. How?\nIt sums the bundle items before applying the discount.');

describe('evaluateDescription', () => {
    const cases: Array<[string, string, string | undefined]> = [
        ['empty body', '', 'has no description'],
        ['whitespace only', '  \n\n\t', 'has no description'],
        ['untouched template', template, 'has no description'],
        ['template with only ticked checkboxes', template.replaceAll('- [ ]', '- [x]'), 'has no description'],
        [
            'only an html comment',
            '<!-- a multi line\ncomment that is long enough on its own but is not authored text -->',
            'has no description',
        ],
        [
            'comment markers wrapped in a comment',
            '<!-<!-- inner -->- outer comment that is long enough on its own -->',
            'has no description',
        ],
        ['too short', 'Fix typo.', `too short (9 of at least ${MIN_LENGTH} characters)`],
        [
            'too short spread over template sections',
            template.replace('### 1. What and why?', '### 1. What and why?\nBug.'),
            `too short (4 of at least ${MIN_LENGTH} characters)`,
        ],
        ['exactly the minimum length', 'a'.repeat(MIN_LENGTH), undefined],
        ['filled template', filledTemplate, undefined],
        [
            'free-form description without the template',
            'The cart total was wrong for bundles.\n\nThis change sums the bundle items before applying the discount.',
            undefined,
        ],
    ];

    for (const [name, body, expected] of cases) {
        test(name, () => {
            const failure = evaluateDescription(body, template);
            if (expected === undefined) {
                assert.equal(failure, undefined);
            } else {
                assert.ok(failure?.includes(expected), `expected "${expected}" in "${failure}"`);
            }
        });
    }

    test('counts multibyte characters once', () => {
        assert.equal(evaluateDescription('ä'.repeat(MIN_LENGTH), template), undefined);
    });

    test('counts every line when the template is missing on the base branch', () => {
        assert.equal(evaluateDescription(template, ''), undefined);
    });
});

describe('checkPullRequestDescription', () => {
    const run = (body: string | null, templatePath?: string) => {
        const calls: string[] = [];
        const core = {
            setFailed: (message: string) => calls.push(`failed: ${message}`),
            info: (message: string) => calls.push(`info: ${message}`),
        };
        checkPullRequestDescription({ core, context: { payload: { pull_request: { body } } }, templatePath });

        return calls;
    };

    test('fails the job on a template-only description', () => {
        const calls = run(template, TEMPLATE_PATH);
        assert.equal(calls.length, 1);
        assert.match(calls[0], /^failed: The pull request has no description/);
    });

    test('passes a filled template', () => {
        assert.deepEqual(run(filledTemplate, TEMPLATE_PATH), ['info: The pull request description is filled in.']);
    });

    test('treats a null body as empty', () => {
        assert.match(run(null, TEMPLATE_PATH)[0], /^failed: The pull request has no description/);
    });

    test('counts every line when the template file is missing', () => {
        assert.deepEqual(run(template, join(TEMPLATE_PATH, 'does-not-exist.md')), [
            'info: The pull request description is filled in.',
        ]);
    });
});
