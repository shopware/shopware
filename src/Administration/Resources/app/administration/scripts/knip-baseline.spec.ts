/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import { spawnSync } from 'child_process';

const KNIP = path.join(__dirname, '../node_modules/knip/bin/knip.js');
const PREPROCESSOR = path.join(__dirname, 'knip-baseline.mjs');
const UNUSED_EXPORT = 'exports|helpers.js|unused';
const FIXED_EXPORT = 'exports|helpers.js|removed';

describe('scripts/knip-baseline', () => {
    let projectDir: string;

    const writeBaseline = (entries: string[]): void => {
        fs.writeFileSync(path.join(projectDir, 'knip-baseline.json'), JSON.stringify(entries));
    };

    const readBaseline = (): string[] => {
        return JSON.parse(fs.readFileSync(path.join(projectDir, 'knip-baseline.json'), 'utf8')) as string[];
    };

    const runKnip = (env: Record<string, string> = {}): { status: number | null; output: string } => {
        const parentEnv = Object.fromEntries(Object.entries(process.env).filter(([name]) => !name.startsWith('KNIP_')));
        const result = spawnSync(
            process.execPath,
            [
                KNIP,
                '--preprocessor',
                PREPROCESSOR,
                '--no-progress',
                '--no-config-hints',
            ],
            { cwd: projectDir, encoding: 'utf8', env: { ...parentEnv, ...env } },
        );

        return { status: result.status, output: `${result.stdout ?? ''}${result.stderr ?? ''}` };
    };

    beforeEach(() => {
        projectDir = fs.mkdtempSync(path.join(os.tmpdir(), 'knip-baseline-'));

        fs.writeFileSync(path.join(projectDir, 'package.json'), JSON.stringify({ name: 'fixture', type: 'module' }));
        fs.writeFileSync(path.join(projectDir, 'knip.json'), JSON.stringify({ entry: ['index.js'], project: ['*.js'] }));
        fs.writeFileSync(path.join(projectDir, 'index.js'), "import { used } from './helpers.js';\n\nconsole.log(used);\n");
        fs.writeFileSync(path.join(projectDir, 'helpers.js'), 'export const used = 1;\nexport const unused = 2;\n');
    });

    afterEach(() => {
        fs.rmSync(projectDir, { recursive: true, force: true });
    });

    it('fails on a finding that is not in the baseline', () => {
        writeBaseline([]);

        const result = runKnip();

        expect(result.status).toBe(1);
        expect(result.output).toContain('unused');
    });

    it('passes when the baseline holds every finding', () => {
        writeBaseline([UNUSED_EXPORT]);

        const result = runKnip();

        expect(result.status).toBe(0);
        expect(result.output).not.toContain('unused');
    });

    it('fails and names the entries that are fixed but still in the baseline', () => {
        writeBaseline([
            UNUSED_EXPORT,
            FIXED_EXPORT,
        ]);

        const result = runKnip();

        expect(result.status).not.toBe(0);
        expect(result.output).toContain('1 baseline entries are fixed');
        expect(result.output).toContain(FIXED_EXPORT);
    });

    it('removes fixed entries from the baseline when pruning', () => {
        writeBaseline([
            UNUSED_EXPORT,
            FIXED_EXPORT,
        ]);

        const result = runKnip({ KNIP_BASELINE_PRUNE: '1' });

        expect(result.status).toBe(0);
        expect(readBaseline()).toEqual([UNUSED_EXPORT]);
    });

    it('never adds a new finding to the baseline when pruning', () => {
        writeBaseline([]);

        const result = runKnip({ KNIP_BASELINE_PRUNE: '1' });

        expect(result.status).toBe(1);
        expect(readBaseline()).toEqual([]);
    });
});
