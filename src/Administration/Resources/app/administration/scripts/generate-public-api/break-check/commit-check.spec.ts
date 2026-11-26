/**
 * @sw-package framework
 *
 * Runs the break check on the commits of a small repository that the tests create.
 */
import fs from 'node:fs';
import path from 'node:path';
import {
    CARD_WITH_TITLE,
    CARD_WITHOUT_TITLE,
    cardFile,
    EXAMPLE_CARD,
    cardTemplate,
    createExampleRepository,
} from '../testing/example-repository';
import type { ExampleRepository } from '../testing/example-repository';
import { compareCommits } from './commit-check';
import type { Finding } from './finding';

describe('scripts/generate-public-api/break-check/commit-check', () => {
    let example: ExampleRepository;

    function compare(base: string, head: string): Finding[] {
        return compareCommits(example.repository, ['v6.8.0.0'], base, head);
    }

    beforeEach(() => {
        example = createExampleRepository('public-api-commit-check-');
    });

    afterEach(() => {
        example.remove();
    });

    it('compares the sources of two commits, whatever the working tree holds', () => {
        const base = example.git('rev-parse', 'HEAD');
        const head = example.commit('feat: remove the title', cardTemplate(CARD_WITHOUT_TITLE));

        fs.writeFileSync(path.join(example.root, cardFile('sw-example-card.html.twig')), CARD_WITH_TITLE);

        expect(compare(base, head)).toEqual([
            expect.objectContaining({
                group: 'Break',
                rule: 'block_missing',
                element: 'component:sw-example-card/block:sw_example_card_title',
                at: `${EXAMPLE_CARD}/sw-example-card.html.twig:1`,
            }),
        ]);
        expect(compare(head, base).map(({ rule }) => rule)).toEqual(['block_added']);
    });

    it('allows a removal that a marker for the version before the next major announced', () => {
        const base = example.commit(
            'chore: deprecate the title',
            cardTemplate(
                CARD_WITH_TITLE.replace(
                    '{% block sw_example_card_title',
                    '{# @deprecated tag:v6.7.0 - Unused. #}{% block sw_example_card_title',
                ),
            ),
        );
        const head = example.commit('feat: remove the title', cardTemplate(CARD_WITHOUT_TITLE));

        expect(compare(base, head).map(({ group, rule }) => `${group} ${rule}`)).toEqual([
            'Allowed deprecated_removal',
        ]);
    });

    it('rejects a revision that git would read as an option', () => {
        expect(() => compare('--output=stolen', 'HEAD')).toThrow();
        expect(fs.existsSync(path.join(example.root, 'stolen'))).toBe(false);
    });

    it('ignores specs and a template that no component uses', () => {
        const base = example.git('rev-parse', 'HEAD');
        const head = example.commit('test: add a spec and an unused template', {
            [cardFile('sw-example-card.spec.js')]: "Shopware.Component.register('sw-spec-only', {});",
            [cardFile('unused.html.twig')]: '{% block sw_example_unused %}{% endblock %}',
        });

        expect(compare(base, head)).toEqual([]);
    });
});
