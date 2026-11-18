import assert from 'node:assert/strict';
import { test } from 'node:test';
import { applyBodyLabels, labelsForBody } from './body-labels.ts';

const toolkit = (item: { body?: string | null; labels?: { name: string }[] }) => {
    const calls: unknown[] = [];

    return {
        calls,
        toolkit: {
            github: {
                rest: {
                    issues: {
                        addLabels: async (options: unknown) => {
                            calls.push(options);
                        },
                    },
                },
            },
            core: { info: () => {} },
            context: { repo: { owner: 'shopware', repo: 'shopware' }, payload: { issue: { number: 42, ...item } } },
        },
    };
};

test('the area picked in the bug report maps to its label', () => {
    assert.deepEqual(labelsForBody('### Affected area / extension\n\nExtension:Commercial', []), ['extension/Commercial']);
    assert.deepEqual(labelsForBody('Platform(Default)', []), ['Platform(Default)']);
    assert.deepEqual(labelsForBody('Extension:Shopware-Payments', []), ['service/shopware-payments']);
});

test('a body without a known area, or without a body at all, yields nothing', () => {
    assert.deepEqual(labelsForBody('Just a pull request description', []), []);
    assert.deepEqual(labelsForBody(null, []), []);
    assert.deepEqual(labelsForBody(undefined, []), []);
});

test('a label already on the item is not added again', () => {
    assert.deepEqual(labelsForBody('Extension:Commercial', ['extension/Commercial']), []);
});

test('only the missing labels are added, existing labels are never sent', async () => {
    const { calls, toolkit: tk } = toolkit({
        body: 'Extension:Commercial Extension:Copilot',
        labels: [{ name: 'milestone/6.7.17.0' }, { name: 'extension/Copilot' }],
    });

    assert.deepEqual(await applyBodyLabels(tk), ['extension/Commercial']);
    assert.deepEqual(calls, [
        { owner: 'shopware', repo: 'shopware', issue_number: 42, labels: ['extension/Commercial'] },
    ]);
});

test('no API call when nothing matches', async () => {
    const { calls, toolkit: tk } = toolkit({ body: 'ci: something', labels: [{ name: 'milestone/6.7.17.0' }] });

    assert.deepEqual(await applyBodyLabels(tk), []);
    assert.deepEqual(calls, []);
});
