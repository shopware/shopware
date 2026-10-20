/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import { spawnSync } from 'child_process';

const ADMIN_DIR = path.join(__dirname, '..');
const BASELINE = 'fallow-baseline.json';
const UNUSED_EXPORT = 'unused-export:helpers.js:unused';
const FIXED_EXPORT = 'unused-export:helpers.js:removed';

type Baseline = Record<string, unknown> & { unused_exports: string[] };

const { scripts } = JSON.parse(fs.readFileSync(path.join(ADMIN_DIR, 'package.json'), 'utf8')) as {
    scripts: Record<string, string>;
};

describe('npm run lint:fallow', () => {
    let projectDir: string;

    const run = (script: string): { status: number | null; output: string } => {
        const result = spawnSync('sh', ['-c', scripts[script]], {
            cwd: projectDir,
            encoding: 'utf8',
            env: {
                ...process.env,
                PATH: `${path.join(ADMIN_DIR, 'node_modules/.bin')}${path.delimiter}${process.env.PATH ?? ''}`,
            },
        });

        return { status: result.status, output: `${result.stdout ?? ''}${result.stderr ?? ''}` };
    };

    const readBaseline = (): Baseline => {
        return JSON.parse(fs.readFileSync(path.join(projectDir, BASELINE), 'utf8')) as Baseline;
    };

    const writeUnusedExports = (entries: string[]): void => {
        fs.writeFileSync(path.join(projectDir, BASELINE), JSON.stringify({ ...readBaseline(), unused_exports: entries }));
    };

    beforeAll(() => {
        projectDir = fs.mkdtempSync(path.join(os.tmpdir(), 'lint-fallow-'));

        fs.writeFileSync(path.join(projectDir, 'package.json'), JSON.stringify({ name: 'fixture', private: true }));
        fs.writeFileSync(path.join(projectDir, '.fallowrc.json'), JSON.stringify({ entry: ['index.js'] }));
        fs.writeFileSync(path.join(projectDir, 'index.js'), "import { used } from './helpers.js';\n\nconsole.log(used);\n");
        fs.writeFileSync(path.join(projectDir, 'helpers.js'), 'export const used = 1;\nexport const unused = 2;\n');

        const saved = spawnSync(
            path.join(ADMIN_DIR, 'node_modules/.bin/fallow'),
            [
                'dead-code',
                '--quiet',
                '--save-baseline',
                BASELINE,
            ],
            { cwd: projectDir, encoding: 'utf8' },
        );
        if (!fs.existsSync(path.join(projectDir, BASELINE))) {
            throw new Error(`Could not save the fixture baseline: ${saved.stderr}`);
        }
    });

    afterAll(() => {
        fs.rmSync(projectDir, { recursive: true, force: true });
    });

    it('passes when the baseline holds every finding', () => {
        writeUnusedExports([UNUSED_EXPORT]);

        expect(run('lint:fallow').status).toBe(0);
    });

    it('fails on a finding that is not in the baseline', () => {
        writeUnusedExports([]);

        const result = run('lint:fallow');

        expect(result.status).toBe(1);
        expect(result.output).toContain('unused');
    });

    it('fails and points to the prune command when a baselined finding is fixed', () => {
        writeUnusedExports([
            UNUSED_EXPORT,
            FIXED_EXPORT,
        ]);

        const result = run('lint:fallow');

        expect(result.status).toBe(1);
        expect(result.output).toContain('matched no current issue');
        expect(result.output).toContain('baselines prune');
    });

    it('removes fixed entries from the baseline when pruning', () => {
        writeUnusedExports([
            UNUSED_EXPORT,
            FIXED_EXPORT,
        ]);

        expect(run('lint:fallow:prune').status).toBe(0);
        expect(readBaseline().unused_exports).toEqual([UNUSED_EXPORT]);
        expect(run('lint:fallow').status).toBe(0);
    });

    it('never adds a new finding to the baseline when pruning', () => {
        writeUnusedExports([]);

        expect(run('lint:fallow:prune').status).toBe(0);
        expect(readBaseline().unused_exports).toEqual([]);
        expect(run('lint:fallow').status).toBe(1);
    });

    it('fails when Fallow cannot read the baseline', () => {
        fs.writeFileSync(path.join(projectDir, BASELINE), 'not json');

        expect(run('lint:fallow').status).toBe(2);
        expect(run('lint:fallow:prune').status).toBe(2);
    });
});
