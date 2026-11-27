/**
 * @sw-package framework
 *
 * The compare page: it reads what to compare from the address or the form, loads both commits from GitHub, runs
 * the break check in the browser and shows the report. `index.html` holds the markup.
 */
import { compareSourceTrees } from '../break-check/break-check';
import { browserContentCache } from './content-cache';
import { createGitHub } from './github';
import type { Comparison, GitHub } from './github';
import type { HttpFetch } from './http';
import { loadSourceTrees } from './remote-source-tree';
import { renderReport } from './report-view';

/**
 * @private
 *
 * What the page needs from the browser and from the build.
 */
export interface PageEnvironment {
    document: Document;
    location: Pick<Location, 'search'>;
    history: Pick<History, 'replaceState'>;
    fetch: HttpFetch;
    caches?: CacheStorage;
    /** `<owner>/<name>` of the repository on GitHub. */
    repository: string;
    /** The path of the Administration folder in the repository. */
    administration: string;
    /** The major flags of the checkout that built the page, as the command takes them from its checkout. */
    majorFlags: readonly string[];
}

/** A pull request, or two revisions: branches, tags or hashes. */
type CompareRequest = { pullRequest: number } | { base: string; head: string };

const FIELDS = [
    'pr',
    'base',
    'head',
] as const;

function requestOf(values: URLSearchParams): CompareRequest | null {
    const pullRequest = Number(values.get('pr'));
    const base = values.get('base')?.trim();
    const head = values.get('head')?.trim();

    if (Number.isInteger(pullRequest) && pullRequest > 0) {
        return { pullRequest };
    }

    return base && head ? { base, head } : null;
}

async function comparisonOf(request: CompareRequest, github: GitHub): Promise<Comparison> {
    if ('pullRequest' in request) {
        return github.comparisonOfPullRequest(request.pullRequest);
    }

    const [base, head] = await Promise.all([
        github.commitOf(request.base),
        github.commitOf(request.head),
    ]);

    return { base, head };
}

/**
 * @private
 *
 * Wires the form of the page and runs the comparison the address names, if it names one.
 */
export function startPage(environment: PageEnvironment): void {
    const { document, majorFlags, repository, administration } = environment;
    const required = <Node extends HTMLElement>(selector: string): Node => {
        const node = document.querySelector<Node>(selector);

        if (node === null) {
            throw new Error(`The page has no ${selector}.`);
        }

        return node;
    };
    const form = required<HTMLFormElement>('#compare-form');
    const submit = required<HTMLButtonElement>('#compare-submit');
    const status = required('#compare-status');
    const progress = required<HTMLProgressElement>('#compare-progress');
    const report = required('#compare-report');
    const cache = browserContentCache(environment.caches);

    const field = (name: string): HTMLInputElement => required<HTMLInputElement>(`#compare-form [name="${name}"]`);

    const say = (message: string, failed = false): void => {
        status.textContent = message;
        status.classList.toggle('is-error', failed);
    };

    const compare = async (request: CompareRequest): Promise<void> => {
        const github = createGitHub({
            repository,
            administration,
            fetch: environment.fetch,
            token: field('token').value.trim() || undefined,
        });

        say('Asking GitHub for the commits.');

        const { base, head } = await comparisonOf(request, github);
        const [baseTree, headTree] = await loadSourceTrees(
            github,
            [
                base,
                head,
            ],
            {
                cache,
                onProgress(loaded, total) {
                    progress.hidden = false;
                    progress.max = total;
                    progress.value = loaded;
                    say(`Loading the sources, ${loaded} of ${total} files.`);
                },
            },
        );

        say('Comparing.');
        // The check blocks the page while it runs, so the browser gets a turn to show the line above first.
        await new Promise((resolve) => {
            setTimeout(resolve);
        });

        renderReport(report, compareSourceTrees(baseTree, headTree, majorFlags), {
            sourceUrl: (at) => github.sourceUrl(head, at),
        });
        say(`Compared ${base.slice(0, 12)} with ${head.slice(0, 12)}.`);
    };

    const run = (request: CompareRequest | null): void => {
        if (request === null) {
            say('Enter a pull request, or a base and a head.', true);

            return;
        }

        submit.disabled = true;
        report.replaceChildren();
        compare(request)
            .catch((error: unknown) => say(error instanceof Error ? error.message : String(error), true))
            .finally(() => {
                submit.disabled = false;
                progress.hidden = true;
            });
    };

    form.onsubmit = (event) => {
        event.preventDefault();

        const values = new URLSearchParams(
            FIELDS.map((name): [string, string] => [name, field(name).value.trim()]).filter(([, value]) => value !== ''),
        );

        try {
            environment.history.replaceState(null, '', `?${values.toString()}`);
        } catch {
            // A page that is opened as a file may not change its address.
        }

        run(requestOf(values));
    };

    const fromAddress = new URLSearchParams(environment.location.search);

    FIELDS.forEach((name) => {
        field(name).value = fromAddress.get(name) ?? '';
    });

    if (FIELDS.some((name) => fromAddress.has(name))) {
        run(requestOf(fromAddress));
    }
}
