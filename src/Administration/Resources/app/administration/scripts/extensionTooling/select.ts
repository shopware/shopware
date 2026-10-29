/**
 * @sw-package framework
 *
 * Extension selection for the extension tooling commands. The grammar —
 * numbers, comma lists, ranges, "all", "writable" — lives in the pure
 * `parseSelection`, separated from the readline I/O in `promptSelection`, so it
 * is unit-testable without a TTY.
 */

import readline from 'node:readline/promises';
import colors from 'picocolors';
import { normalizeSelection, projectHasOwnedConfig } from './shared';
import type { ExtensionToolingProject } from './shared';

export type Selection = { names: string[] } | 'all' | 'cancel' | { error: string };

export function parseSelection(input: string, projects: ExtensionToolingProject[]): Selection {
    const trimmed = input.trim().toLowerCase();

    if (trimmed === '') {
        return 'cancel';
    }

    if (trimmed === 'a' || trimmed === 'all') {
        return 'all';
    }

    if (trimmed === 'w' || trimmed === 'writable') {
        const names = projects.filter((project) => !project.vendor).map((project) => project.name);

        return names.length > 0 ? { names } : { error: 'No writable (non-vendor) extensions to select.' };
    }

    const indices = new Set<number>();

    for (const token of trimmed.split(',')) {
        const part = token.trim();

        if (part === '') {
            continue;
        }

        const range = part.match(/^(\d+)-(\d+)$/);

        if (range) {
            const start = Number(range[1]);
            const end = Number(range[2]);

            if (start < 1 || end > projects.length || start > end) {
                return { error: `Range "${part}" is out of bounds (1-${projects.length}).` };
            }

            for (let index = start; index <= end; index += 1) {
                indices.add(index);
            }

            continue;
        }

        if (!/^\d+$/.test(part)) {
            return { error: `"${part}" is not a number, a range (e.g. 2-4), "a" (all), or "w" (writable).` };
        }

        const index = Number(part);

        if (index < 1 || index > projects.length) {
            return { error: `"${part}" is out of range (1-${projects.length}).` };
        }

        indices.add(index);
    }

    if (indices.size === 0) {
        return 'cancel';
    }

    return { names: [...indices].sort((left, right) => left - right).map((index) => projects[index - 1].name) };
}

function describe(project: ExtensionToolingProject): string {
    const location = project.vendor ? 'vendor' : project.basePath;
    const configMode = projectHasOwnedConfig(project) ? 'custom config' : 'zero-config';
    const moduleCount = project.technicalNames.length;

    return `${location} · ${configMode} · ${moduleCount === 1 ? '1 module' : `${moduleCount} modules`}`;
}

/** Renders the numbered menu and resolves the user's choice, re-prompting on invalid input. */
export async function promptSelection(
    projects: ExtensionToolingProject[],
    title: string,
): Promise<{ names: string[] } | 'all' | 'cancel'> {
    const terminal = readline.createInterface({ input: process.stdin, output: process.stdout });

    try {
        process.stdout.write(colors.bold(`\n${title}\n\n`));

        const numberWidth = String(projects.length).length;

        projects.forEach((project, index) => {
            const number = colors.cyan(`${String(index + 1).padStart(numberWidth, ' ')})`);

            process.stdout.write(`  ${number} ${project.name.padEnd(28, ' ')} ${colors.dim(describe(project))}\n`);
        });

        for (;;) {
            const answer = await terminal.question(
                colors.dim("\nEnter numbers (e.g. 1,3 or 2-4), 'a'=all, 'w'=writable only, Enter=cancel:\n") + '> ',
            );
            const selection = parseSelection(answer, projects);

            if (typeof selection === 'object' && 'error' in selection) {
                process.stdout.write(colors.yellow(`  ${selection.error}\n`));

                continue;
            }

            return selection;
        }
    } finally {
        terminal.close();
    }
}

export interface CliSelectionOptions {
    /** The raw --only value. */
    only: string | undefined;
    all: boolean;
    /** The projects the interactive picker offers; only called on a TTY. */
    candidates: () => ExtensionToolingProject[];
    messages: {
        title: string;
        /** Printed when the picker has nothing to offer. */
        empty: string;
        /** Printed when a non-interactive run without --only or --all falls back to every extension. */
        fallback: string;
    };
}

export interface CliSelection {
    /** The names to run; undefined runs every extension. */
    only: string[] | undefined;
    /** The names given via --only, which may include vendor extensions on purpose. */
    explicitOnly: string[];
}

/** Resolves --only, --all and the interactive picker; null when there is nothing to run. */
export async function resolveCliSelection(options: CliSelectionOptions): Promise<CliSelection | null> {
    if (options.only !== undefined) {
        const names = normalizeSelection(options.only);

        return { only: names, explicitOnly: names };
    }

    if (options.all) {
        return { only: undefined, explicitOnly: [] };
    }

    if (!process.stdin.isTTY || !process.stdout.isTTY) {
        console.log(options.messages.fallback);

        return { only: undefined, explicitOnly: [] };
    }

    const projects = options.candidates();

    if (projects.length === 0) {
        console.log(options.messages.empty);

        return null;
    }

    const choice = await promptSelection(projects, options.messages.title);

    if (choice === 'cancel') {
        console.log('Nothing selected.');

        return null;
    }

    return { only: choice === 'all' ? undefined : choice.names, explicitOnly: [] };
}
