/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { createAnalysisContext } from '../analysis-context';
import type { ComponentEntry } from '../types';
import { extractComponents } from '.';

const FIXTURE_ROOT = path.join(__dirname, '__fixtures__', 'admin-root');
const ADMIN_NODE_MODULES = path.resolve(__dirname, '../../../../node_modules');
const MODULE_DIR = 'src/module/sw-fixture';
const TYPED_FILE = `${MODULE_DIR}/component/sw-fixture-typed/index.ts`;

describe('type texts of extracted components', () => {
    let tmpDir: string;

    const copyFixture = (name: string, change: (root: string) => void): string => {
        const root = path.join(tmpDir, name);

        fs.cpSync(FIXTURE_ROOT, root, { recursive: true });
        fs.symlinkSync(ADMIN_NODE_MODULES, path.join(root, 'node_modules'), 'dir');
        change(root);

        return root;
    };

    const extract = async (root: string): Promise<ComponentEntry[]> =>
        (await extractComponents(createAnalysisContext(root))).result;

    const settingsType = (components: ComponentEntry[]): string | undefined =>
        components.find(({ name }) => name === 'sw-fixture-typed')?.data.find(({ name }) => name === 'settings')?.type;

    beforeEach(() => {
        tmpDir = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-type-text-')));
    });

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it('does not depend on the order in which an unrelated component creates literal types', async () => {
        const original = await extract(FIXTURE_ROOT);
        const perturbed = await extract(
            copyFixture('perturbed', (root) => {
                const componentDir = path.join(root, MODULE_DIR, 'component', 'sw-fixture-aaa');

                fs.mkdirSync(componentDir);
                fs.writeFileSync(
                    path.join(componentDir, 'index.ts'),
                    [
                        'export default Shopware.Component.wrapComponentConfig({',
                        '    methods: {',
                        "        align(): 'end' | 'small' | 'start' | 'large' {",
                        "            return 'small';",
                        '        },',
                        '    },',
                        '});',
                        '',
                    ].join('\n'),
                );
                fs.appendFileSync(
                    path.join(root, MODULE_DIR, 'index.js'),
                    "Shopware.Component.register('sw-fixture-aaa', () => import('./component/sw-fixture-aaa'));\n",
                );
            }),
        );

        expect(perturbed[0].methods[0].returnType).toBe('"end" | "large" | "small" | "start"');
        expect(JSON.stringify(perturbed.slice(1))).toBe(JSON.stringify(original));
    }, 120_000);

    it('changes the stored text of a long type when its cut part changes', async () => {
        const original = settingsType(await extract(FIXTURE_ROOT));
        const changed = settingsType(
            await extract(
                copyFixture('changed', (root) => {
                    const file = path.join(root, TYPED_FILE);

                    fs.writeFileSync(
                        file,
                        fs
                            .readFileSync(file, 'utf8')
                            .replace(
                                'thirdOptionWithAVeryLongName: string;',
                                'thirdOptionWithAVeryLongName: string | null;',
                            ),
                    );
                }),
            ),
        );

        expect(original).toMatch(/… \[\+\d+ chars #[0-9a-f]{8}\]$/);
        expect(changed).toMatch(/… \[\+\d+ chars #[0-9a-f]{8}\]$/);
        expect(changed?.split('…')[0]).toBe(original?.split('…')[0]);
        expect(changed).not.toBe(original);
    }, 120_000);
});
