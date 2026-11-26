/**
 * @sw-package framework
 *
 * A git repository in a temporary folder with an Administration that registers one component, `sw-example-card`,
 * for the specs that run the break check on commits.
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { repositoryOf } from '../source/git-source-tree';
import type { GitRepository } from '../source/git-source-tree';

const EXAMPLE_ADMINISTRATION = 'src/Administration/Resources/app/administration';

/** @private */
export const EXAMPLE_CARD = 'src/module/sw-example/component/sw-example-card';

/** @private */
export const CARD_WITH_TITLE = '{% block sw_example_card %}{% block sw_example_card_title %}{% endblock %}{% endblock %}';

/** @private */
export const CARD_WITHOUT_TITLE = '{% block sw_example_card %}{% endblock %}';

/** @private */
export interface ExampleRepository {
    root: string;
    repository: GitRepository;
    git: (...args: string[]) => string;
    /** Commits the files, given by their path in the repository, and returns the hash of the commit. */
    commit: (subject: string, files: Record<string, string>) => string;
    remove: () => void;
}

/**
 * @private
 *
 * The path of a file of `sw-example-card` in the repository.
 */
export function cardFile(name: string): string {
    return `${EXAMPLE_ADMINISTRATION}/${EXAMPLE_CARD}/${name}`;
}

/** @private */
export function cardTemplate(content: string): Record<string, string> {
    return { [cardFile('sw-example-card.html.twig')]: content };
}

/**
 * @private
 *
 * The first commit registers `sw-example-card` with the template `CARD_WITH_TITLE`.
 */
export function createExampleRepository(prefix: string): ExampleRepository {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), prefix));

    const git = (...args: string[]): string =>
        execFileSync(
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
            { encoding: 'utf8' },
        ).trim();

    const commit = (subject: string, files: Record<string, string>): string => {
        Object.entries(files).forEach(([file, content]) => {
            fs.mkdirSync(path.dirname(path.join(root, file)), { recursive: true });
            fs.writeFileSync(path.join(root, file), content);
        });
        git('add', '--all');
        git('commit', '--quiet', '--message', subject);

        return git('rev-parse', 'HEAD');
    };

    git('init', '--quiet');
    commit('chore: add the card', {
        [`${EXAMPLE_ADMINISTRATION}/src/module/sw-example/index.js`]:
            "Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));",
        [cardFile('index.js')]: "import template from './sw-example-card.html.twig';\n\nexport default { template };",
        ...cardTemplate(CARD_WITH_TITLE),
    });

    return {
        root,
        repository: repositoryOf(path.join(root, EXAMPLE_ADMINISTRATION)),
        git,
        commit,
        remove: () => fs.rmSync(root, { recursive: true, force: true }),
    };
}
