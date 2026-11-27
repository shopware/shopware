/**
 * @sw-package framework
 * @jest-environment jsdom
 */
import fs from 'node:fs';
import path from 'node:path';
import type { HttpResponse } from './http';
import { startPage } from './page';
import {
    ADMINISTRATION,
    BASE_COMMIT,
    HEAD_COMMIT,
    PULL_REQUEST,
    REPOSITORY,
    fixtureCase,
    fixtureGitHub,
} from './testing/fixture-github';
import { respond } from './testing/http-response';

const MARKUP = fs.readFileSync(path.join(__dirname, 'index.html'), 'utf8');
const API = `https://api.github.com/repos/${REPOSITORY}`;

function one<Node extends HTMLElement = HTMLElement>(selector: string): Node {
    const node = document.querySelector<Node>(selector);

    if (node === null) {
        throw new Error(`The page has no ${selector}.`);
    }

    return node;
}

describe('the compare page', () => {
    const blockMoved = fixtureCase('block-moved');

    function open(search: string, overrides?: (url: string) => HttpResponse | undefined) {
        const github = fixtureGitHub(blockMoved, overrides);
        const addresses: string[] = [];
        const authorizations: Array<string | undefined> = [];

        document.documentElement.innerHTML = MARKUP;
        startPage({
            document,
            location: { search },
            history: { replaceState: (_state, _unused, url) => addresses.push(String(url)) },
            fetch: (url, init) => {
                authorizations.push(init?.headers.Authorization);

                return github.fetch(url, init);
            },
            repository: REPOSITORY,
            administration: ADMINISTRATION,
            majorFlags: blockMoved.majorFlags,
        });

        return {
            requests: github.requests,
            addresses,
            authorizations,
            field: (name: string) => one<HTMLInputElement>(`[name="${name}"]`),
            status: () => one('#compare-status'),
            report: () => one('#compare-report'),
            compare: () => one('#compare-submit').click(),
            async finished() {
                for (let turn = 0; turn < 500 && one<HTMLButtonElement>('#compare-submit').disabled; turn += 1) {
                    await new Promise((resolve) => {
                        setTimeout(resolve, 1);
                    });
                }
            },
        };
    }

    it('compares the pull request that the address names', async () => {
        const page = open(`?pr=${PULL_REQUEST}`);

        await page.finished();

        expect(page.field('pr').value).toBe(String(PULL_REQUEST));
        expect(page.report().querySelector('pre')?.textContent).toBe(blockMoved.expectedSummary);
        expect(page.status().textContent).toBe(`Compared ${BASE_COMMIT.slice(0, 12)} with ${HEAD_COMMIT.slice(0, 12)}.`);
        expect(page.report().querySelector('a')?.getAttribute('href')).toBe(
            `https://github.com/${REPOSITORY}/blob/${HEAD_COMMIT}/${ADMINISTRATION}/src/app/component/base/sw-example-state-card/sw-example-state-card.html.twig#L59`,
        );
    });

    it('compares the two revisions of the form and puts them into the address', async () => {
        const page = open('', (url) => {
            if (url === `${API}/commits/v6.7.0.0`) {
                return respond(BASE_COMMIT);
            }

            return url === `${API}/commits/trunk` ? respond(HEAD_COMMIT) : undefined;
        });

        page.field('base').value = 'v6.7.0.0';
        page.field('head').value = 'trunk';
        page.compare();
        await page.finished();

        expect(page.addresses).toEqual(['?base=v6.7.0.0&head=trunk']);
        expect(page.report().querySelector('h2')?.textContent).toBe('Public API check failed: 2 unannounced breaks.');
    });

    it('waits for input when the address names nothing', () => {
        const page = open('');

        expect(page.requests).toEqual([]);
        expect(page.status().textContent).toBe('');
    });

    it('asks for a pull request or two revisions when the form holds neither', () => {
        const page = open('');

        page.field('base').value = 'trunk';
        page.compare();

        expect(page.status().textContent).toBe('Enter a pull request, or a base and a head.');
        expect(page.requests).toEqual([]);
    });

    it('shows what GitHub answered instead of a report when the comparison fails', async () => {
        const page = open('?pr=404');

        await page.finished();

        expect(page.status().textContent).toBe('GitHub does not know pull request #404.');
        expect(page.status().classList.contains('is-error')).toBe(true);
        expect(page.report().childElementCount).toBe(0);
    });

    it('sends the token of the form to GitHub', async () => {
        const page = open('');

        page.field('pr').value = String(PULL_REQUEST);
        page.field('token').value = 'secret';
        page.compare();
        await page.finished();

        expect(page.authorizations).toContain('Bearer secret');
    });
});
