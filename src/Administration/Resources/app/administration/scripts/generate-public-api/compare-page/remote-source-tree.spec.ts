/**
 * @sw-package framework
 */
import { createGitHub } from './github';
import { compareSourceTrees } from '../break-check/break-check';
import type { Finding } from '../break-check/finding';
import { loadSourceTrees } from './remote-source-tree';
import type { ContentCache, LoadOptions, RemoteSource } from './remote-source-tree';
import { ADMINISTRATION, BASE_COMMIT, HEAD_COMMIT, REPOSITORY, fixtureCase, fixtureGitHub } from './testing/fixture-github';

function memoryCache(): ContentCache {
    const contents = new Map<string, string>();

    return {
        get: (hash) => Promise.resolve(contents.get(hash)),
        set: (hash, content) => {
            contents.set(hash, content);

            return Promise.resolve();
        },
    };
}

describe('the break check on commits of a remote', () => {
    const blockMoved = fixtureCase('block-moved');

    async function compare(remote: RemoteSource, options?: LoadOptions): Promise<Finding[]> {
        const [base, head] = await loadSourceTrees(
            remote,
            [
                BASE_COMMIT,
                HEAD_COMMIT,
            ],
            options,
        );

        return compareSourceTrees(base, head, blockMoved.majorFlags);
    }

    function remoteOf(github = fixtureGitHub(blockMoved)) {
        return {
            github,
            remote: createGitHub({ repository: REPOSITORY, administration: ADMINISTRATION, fetch: github.fetch }),
            fileRequests: () => github.requests.filter((url) => url.startsWith('https://raw.githubusercontent.com/')),
        };
    }

    it('reports the findings the break check reports for a checkout of the same sources', async () => {
        const { remote } = remoteOf();

        const findings = await compare(remote);

        expect(findings).toEqual(blockMoved.expected.findings);
        expect(findings.map(({ rule }) => rule)).toContain('block_moved_parent');
    });

    it('loads the files that can declare public API and leaves the others on the remote', async () => {
        const { remote, fileRequests } = remoteOf();

        await compare(remote);

        const loaded = fileRequests().map((url) => url.split(`${ADMINISTRATION}/`)[1]);

        expect(loaded).toContain('src/app/component/base/sw-example-state-card/index.js');
        expect(loaded).not.toContain('src/app/component/example.spec.js');
    });

    it('loads a file once that both commits share', async () => {
        const { remote, fileRequests } = remoteOf();

        await compare(remote);

        const registrations = fileRequests().filter((url) => url.endsWith('/src/app/component/index.ts'));

        expect(registrations).toHaveLength(1);
    });

    it('takes the files of an earlier check from the cache', async () => {
        const cache = memoryCache();
        const first = remoteOf();
        const second = remoteOf();

        await compare(first.remote, { cache });

        const findings = await compare(second.remote, { cache });

        expect(first.fileRequests()).not.toHaveLength(0);
        expect(second.fileRequests()).toHaveLength(0);
        expect(findings).toEqual(blockMoved.expected.findings);
    });

    it('reports the progress until every file is loaded', async () => {
        const { remote, fileRequests } = remoteOf();
        const progress: Array<[number, number]> = [];

        await compare(remote, { onProgress: (loaded, total) => progress.push([loaded, total]) });

        const total = fileRequests().length;

        expect(progress.at(-1)).toEqual([total, total]);
        expect(progress).toHaveLength(total);
    });
});
