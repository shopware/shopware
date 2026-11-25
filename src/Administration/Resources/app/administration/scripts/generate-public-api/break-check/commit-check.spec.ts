/**
 * @sw-package framework
 *
 * Runs the break check on the commits of a small repository that the tests create.
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { repositoryOf } from '../source/git-source-tree';
import { compareCommits } from './commit-check';
import type { Finding } from './finding';

const ADMINISTRATION = 'src/Administration/Resources/app/administration';

const CARD = 'src/module/sw-example/component/sw-example-card';

const WITH_TITLE = '{% block sw_example_card %}{% block sw_example_card_title %}{% endblock %}{% endblock %}';

const WITHOUT_TITLE = '{% block sw_example_card %}{% endblock %}';

describe('scripts/generate-public-api/break-check/commit-check', () => {
    let root: string;
    let compare: (base: string, head: string) => Finding[];

    function git(...args: string[]): string {
        return execFileSync(
            'git',
            [
                '-C',
                root,
                '-c',
                'user.name=Test',
                '-c',
                'user.email=test@example.com',
                ...args,
            ],
            {
                encoding: 'utf8',
            },
        ).trim();
    }

    /** Commits the files, given by their path in the repository, and returns the hash of the commit. */
    function commit(subject: string, files: Record<string, string>): string {
        Object.entries(files).forEach(([file, content]) => {
            fs.mkdirSync(path.dirname(path.join(root, file)), { recursive: true });
            fs.writeFileSync(path.join(root, file), content);
        });
        git('add', '--all');
        git('commit', '--quiet', '--message', subject);

        return git('rev-parse', 'HEAD');
    }

    function template(content: string): Record<string, string> {
        return { [`${ADMINISTRATION}/${CARD}/sw-example-card.html.twig`]: content };
    }

    beforeEach(() => {
        root = fs.mkdtempSync(path.join(os.tmpdir(), 'public-api-commit-check-'));
        git('init', '--quiet');
        commit('chore: add the card', {
            [`${ADMINISTRATION}/src/module/sw-example/index.js`]:
                "Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));",
            [`${ADMINISTRATION}/${CARD}/index.js`]:
                "import template from './sw-example-card.html.twig';\n\nexport default { template };",
            ...template(WITH_TITLE),
        });
        compare = (base, head) => compareCommits(repositoryOf(path.join(root, ADMINISTRATION)), ['v6.8.0.0'], base, head);
    });

    afterEach(() => {
        fs.rmSync(root, { recursive: true, force: true });
    });

    it('compares the sources of two commits, whatever the working tree holds', () => {
        const base = git('rev-parse', 'HEAD');
        const head = commit('feat: remove the title', template(WITHOUT_TITLE));

        fs.writeFileSync(path.join(root, ADMINISTRATION, CARD, 'sw-example-card.html.twig'), WITH_TITLE);

        expect(compare(base, head)).toEqual([
            expect.objectContaining({
                group: 'Break',
                rule: 'block_missing',
                element: 'component:sw-example-card/block:sw_example_card_title',
                at: `${CARD}/sw-example-card.html.twig:1`,
            }),
        ]);
        expect(compare(head, base).map(({ rule }) => rule)).toEqual(['block_added']);
    });

    it('allows a removal that a marker for the version before the next major announced', () => {
        const base = commit(
            'chore: deprecate the title',
            template(
                WITH_TITLE.replace(
                    '{% block sw_example_card_title',
                    '{# @deprecated tag:v6.7.0 - Unused. #}{% block sw_example_card_title',
                ),
            ),
        );
        const head = commit('feat: remove the title', template(WITHOUT_TITLE));

        expect(compare(base, head).map(({ group, rule }) => `${group} ${rule}`)).toEqual([
            'Allowed deprecated_removal',
        ]);
    });

    it('rejects a revision that git would read as an option', () => {
        expect(() => compare('--output=stolen', 'HEAD')).toThrow();
        expect(fs.existsSync(path.join(root, 'stolen'))).toBe(false);
    });

    it('ignores specs and a template that no component uses', () => {
        const base = git('rev-parse', 'HEAD');
        const head = commit('test: add a spec and an unused template', {
            [`${ADMINISTRATION}/${CARD}/sw-example-card.spec.js`]: "Shopware.Component.register('sw-spec-only', {});",
            [`${ADMINISTRATION}/${CARD}/unused.html.twig`]: '{% block sw_example_unused %}{% endblock %}',
        });

        expect(compare(base, head)).toEqual([]);
    });
});
