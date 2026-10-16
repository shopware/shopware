/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import { spawnSync } from 'child_process';

const SCRIPT = path.join(__dirname, 'init-eslint-baseline.mjs');
const BASELINE = 'eslint-suppressions.json';

describe('scripts/init-eslint-baseline', () => {
    let projectDir: string;

    // The fake lint script records the arguments it got as the baseline
    const writeProject = (lintExitCode = 0): void => {
        fs.writeFileSync(
            path.join(projectDir, 'lint.js'),
            `require('fs').writeFileSync('${BASELINE}', JSON.stringify(process.argv.slice(2)));\nprocess.exit(${lintExitCode});\n`,
        );
        fs.writeFileSync(
            path.join(projectDir, 'package.json'),
            JSON.stringify({ name: 'fixture', scripts: { lint: 'node lint.js' } }),
        );
    };

    const run = (): { status: number | null; output: string } => {
        const result = spawnSync(process.execPath, [SCRIPT], { cwd: projectDir, encoding: 'utf8' });

        return { status: result.status, output: `${result.stdout ?? ''}${result.stderr ?? ''}` };
    };

    const readBaseline = (): string => fs.readFileSync(path.join(projectDir, BASELINE), 'utf8');

    beforeEach(() => {
        projectDir = fs.mkdtempSync(path.join(os.tmpdir(), 'init-eslint-baseline-'));
    });

    afterEach(() => {
        fs.rmSync(projectDir, { recursive: true, force: true });
    });

    it('creates the baseline by running the lint script with --suppress-all', () => {
        writeProject();

        const result = run();

        expect(result.status).toBe(0);
        expect(JSON.parse(readBaseline())).toEqual(['--suppress-all']);
    });

    it('refuses to overwrite an existing baseline', () => {
        writeProject();
        fs.writeFileSync(path.join(projectDir, BASELINE), '{"src/a.js":{}}');

        const result = run();

        expect(result.status).toBe(1);
        expect(result.output).toContain('eslint-suppressions.json already exists');
        expect(readBaseline()).toBe('{"src/a.js":{}}');
    });

    it('fails when the lint script fails', () => {
        writeProject(3);

        expect(run().status).not.toBe(0);
    });
});
