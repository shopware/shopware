/**
 * @sw-package framework
 *
 * Serves a case of `testing/__fixtures__` the way GitHub serves two commits, for the specs of the compare page.
 * The `before` folder is the base commit and the `after` folder the head commit of one pull request.
 */
import { createHash } from 'node:crypto';
import path from 'node:path';
import type { HttpFetch, HttpResponse } from '../http';
import { readSourceTree } from '../../source/source-tree';
import type { SourceTree } from '../../source/source-tree';
import { loadFixtureCases } from '../../testing/fixture-runner';
import type { LoadedFixtureCase } from '../../testing/fixture-runner';
import { respond } from './http-response';

/** @private */
export const REPOSITORY = 'shopware/shopware';

/** @private */
export const ADMINISTRATION = 'src/Administration/Resources/app/administration';

/** @private */
export const PULL_REQUEST = 7;

/** @private */
export const BASE_COMMIT = 'b'.repeat(40);

/** @private */
export const HEAD_COMMIT = 'a'.repeat(40);

const API = `https://api.github.com/repos/${REPOSITORY}`;
const RAW = `https://raw.githubusercontent.com/${REPOSITORY}`;

/** @private */
export function fixtureCase(name: string): LoadedFixtureCase {
    const found = loadFixtureCases(path.join(__dirname, '../../testing/__fixtures__')).find((one) => one.name === name);

    if (found === undefined) {
        throw new Error(`There is no fixture case ${name}.`);
    }

    return found;
}

function hashOf(content: string): string {
    return createHash('sha1').update(content).digest('hex');
}

/** @private */
export interface FixtureGitHub {
    fetch: HttpFetch;
    /** Every address that was requested, in order. */
    requests: string[];
}

type Commits = Record<string, SourceTree | undefined>;

/** Answers an address, or leaves it to the next route. */
type Route = (url: string, commits: Commits) => HttpResponse | undefined;

const TREE_ADDRESS = new RegExp(`^${API}/git/trees/([0-9a-f]+)\\?recursive=1$`);
const FILE_ADDRESS = new RegExp(`^${RAW}/([0-9a-f]+)/${ADMINISTRATION}/(.+)$`);

/** The file list of a commit, with entries around it that the page has to leave out. */
function fileList(tree: SourceTree): HttpResponse {
    return respond({
        truncated: false,
        tree: [
            ...tree.files.map((file) => ({
                path: `${ADMINISTRATION}/${file}`,
                type: 'blob',
                sha: hashOf(tree.read(file)),
            })),
            { path: `${ADMINISTRATION}/src`, type: 'tree', sha: 'tree' },
            { path: `${ADMINISTRATION}/src/app/component/example.spec.js`, type: 'blob', sha: 'spec' },
            { path: 'src/Core/Kernel.php', type: 'blob', sha: 'php' },
        ],
    });
}

const ROUTES: readonly Route[] = [
    (url) =>
        url === `${API}/pulls/${PULL_REQUEST}` ? respond({ head: { sha: HEAD_COMMIT }, base: { ref: 'trunk' } }) : undefined,
    (url) =>
        url === `${API}/compare/trunk...${HEAD_COMMIT}?per_page=1&page=2`
            ? respond({ merge_base_commit: { sha: BASE_COMMIT } })
            : undefined,
    (url, commits) => {
        const tree = commits[TREE_ADDRESS.exec(url)?.[1] ?? ''];

        return tree && fileList(tree);
    },
    (url, commits) => {
        const [, commit = '', file = ''] = FILE_ADDRESS.exec(url) ?? [];
        const tree = commits[commit];

        return tree?.has(file) ? respond(tree.read(file)) : undefined;
    },
];

/**
 * @private
 *
 * GitHub with one pull request, `PULL_REQUEST`, from `BASE_COMMIT` to `HEAD_COMMIT`. `overrides` answers an
 * address before the fixture does.
 */
export function fixtureGitHub(
    { beforeRoot, afterRoot }: LoadedFixtureCase,
    overrides: Route = () => undefined,
): FixtureGitHub {
    const commits: Commits = {
        [BASE_COMMIT]: readSourceTree(beforeRoot),
        [HEAD_COMMIT]: readSourceTree(afterRoot),
    };
    const requests: string[] = [];
    const routes = [
        overrides,
        ...ROUTES,
    ];

    return {
        requests,
        fetch: (url) => {
            requests.push(url);

            const answer = routes.reduce<HttpResponse | undefined>(
                (found, route) => found ?? route(url, commits),
                undefined,
            );

            return Promise.resolve(answer ?? respond({ message: 'Not Found' }, 404));
        },
    };
}
