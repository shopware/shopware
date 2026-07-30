/**
 * @sw-package framework
 */

import { EXIT_OK, EXIT_TOOL_ERROR, EXIT_USAGE } from './report';
import { runSetup } from './setup';
import type { FarmResult } from './resolution';

function setup(result: Partial<FarmResult> = {}) {
    const out: string[] = [];
    const err: string[] = [];
    const calls: { projectRoot: string; administrationRoot: string }[] = [];

    return {
        out,
        err,
        calls,
        run: (argv: string[]) =>
            runSetup(argv, {
                io: {
                    out: (text) => out.push(text),
                    err: (text) => err.push(text),
                },
                projectRoot: '/project',
                administrationRoot: '/project/src/Administration/Resources/app/administration',
                build: (projectRoot, administrationRoot) => {
                    calls.push({ projectRoot, administrationRoot });

                    return {
                        farmPath: '/project/node_modules',
                        created: 1072,
                        failures: [],
                        danglingEntries: [],
                        refusal: null,
                        warnings: [],
                        ...result,
                    };
                },
            }),
    };
}

describe('scripts/extensionTooling/setup', () => {
    it('exits 0 and reports how many entries were linked', () => {
        const cli = setup();

        expect(cli.run([])).toBe(EXIT_OK);
        expect(cli.out.join('')).toContain('Linked the installed Administration into node_modules: 1072 entries.');
        expect(cli.calls[0]).toEqual({
            projectRoot: '/project',
            administrationRoot: '/project/src/Administration/Resources/app/administration',
        });
    });

    it('exits 2 on an unknown option without building anything', () => {
        const cli = setup();

        expect(cli.run(['--nope'])).toBe(EXIT_USAGE);
        expect(cli.calls).toHaveLength(0);
        expect(cli.err.join('')).toContain('Usage: administration:extension:setup');
    });

    it('exits 2 on a positional argument — there is nothing to name', () => {
        const cli = setup();

        expect(cli.run(['SwagExample'])).toBe(EXIT_USAGE);
        expect(cli.calls).toHaveLength(0);
    });

    it('prints the help without building anything', () => {
        const cli = setup();

        expect(cli.run(['--help'])).toBe(EXIT_OK);
        expect(cli.calls).toHaveLength(0);
        expect(cli.out.join('')).toContain('Exit codes:');
    });

    it('exits 3 on a refusal and says why', () => {
        const cli = setup({ refusal: 'node_modules exists and was not created by this command.', created: 0 });

        expect(cli.run([])).toBe(EXIT_TOOL_ERROR);
        expect(cli.err.join('')).toContain('tool error: node_modules exists and was not created by this command.');
    });

    it('exits 3 when not a single link could be created', () => {
        const cli = setup({ created: 0, failures: [{ path: '/project/node_modules/vue', message: 'EPERM' }] });

        expect(cli.run([])).toBe(EXIT_TOOL_ERROR);
        expect(cli.err.join('')).toContain('Not a single link was created');
    });

    it('reports partial failures but keeps exit 0 — the checker does not depend on the farm', () => {
        const cli = setup({
            created: 1070,
            failures: [{ path: '/project/node_modules/vue', message: 'EPERM: operation not permitted' }],
        });

        expect(cli.run([])).toBe(EXIT_OK);

        const output = cli.out.join('');

        expect(output).toContain('1 links could not be created');
        expect(output).toContain('/project/node_modules/vue: EPERM: operation not permitted');
        expect(output).toContain('Developer Mode');
        expect(output).toContain('does not depend on these links and keeps working');
    });

    it('names skipped entries of a stale Administration node_modules', () => {
        const cli = setup({ danglingEntries: ['left-over-package'] });

        expect(cli.run([])).toBe(EXIT_OK);
        expect(cli.out.join('')).toContain('Skipped 1 unreadable entries');
        expect(cli.out.join('')).toContain('left-over-package');
    });

    it('passes the npm-owned-root warning through', () => {
        const cli = setup({ warnings: ['The project root has a package.json, so npm may manage node_modules there.'] });

        expect(cli.run([])).toBe(EXIT_OK);
        expect(cli.out.join('')).toContain('warning: The project root has a package.json');
    });
});
