/**
 * @sw-package framework
 */

import * as path from 'node:path';
import { createAnalysisContext } from './analysis-context';
import { createSnapshot, type SnapshotReuse } from './create-snapshot';
import { extractComponents, type ComponentExtractionState } from './components';
import { extractShopwareObject } from './shopware-object';
import { EXTRACTOR_VERSION, type ComponentEntry, type ShopwareObjectNode } from './types';

jest.mock('./components');
jest.mock('./shopware-object');

const FIXTURE_ROOT = path.join(__dirname, '..', '__fixtures__', 'admin-root');

const meta = { visibility: 'public', deprecation: null, location: null } as const;

const root: ShopwareObjectNode = {
    ...meta,
    path: 'Shopware',
    name: 'Shopware',
    kind: 'object',
    signature: 'object',
    callSignatures: [],
    typeSource: 'typescript',
    truncated: false,
    children: [],
};

const component = (name: string): ComponentEntry => ({
    ...meta,
    name,
    registration: 'register',
    overriddenBy: [],
    extends: null,
    packageName: null,
    language: 'ts',
    sourceFile: `src/app/component/${name}/index.ts`,
    templateFile: null,
    props: [],
    emits: [],
    slots: [],
    data: [],
    computed: [],
    methods: [],
    inject: [],
    mixins: [],
    blocks: [],
});

const componentState: ComponentExtractionState = {
    adminRoot: FIXTURE_ROOT,
    discovery: null,
    fileHashes: new Map(),
    components: new Map(),
};

describe('createSnapshot', () => {
    beforeEach(() => {
        jest.mocked(extractShopwareObject).mockResolvedValue({
            result: root,
            diagnostics: [{ file: 'src/core/shopware.ts', message: 'object problem' }],
        });
        jest.mocked(extractComponents).mockResolvedValue({
            result: [
                component('sw-zeta'),
                component('sw-alpha'),
            ],
            diagnostics: [{ file: null, message: 'component problem' }],
            state: componentState,
            extracted: [],
        });
    });

    it('combines both extractions with the snapshot metadata', async () => {
        const context = createAnalysisContext(FIXTURE_ROOT);
        const snapshot = await createSnapshot({ adminRoot: FIXTURE_ROOT, ref: 'trunk', commit: 'abc123', context });

        expect(snapshot).toMatchObject({
            formatVersion: 1,
            extractorVersion: EXTRACTOR_VERSION,
            ref: 'trunk',
            commit: 'abc123',
            shopwareObject: root,
            diagnostics: [
                { file: 'src/core/shopware.ts', message: 'object problem' },
                { file: null, message: 'component problem' },
            ],
        });
        expect(snapshot.components.map(({ name }) => name)).toEqual([
            'sw-alpha',
            'sw-zeta',
        ]);
        expect(jest.mocked(extractComponents)).toHaveBeenCalledWith(context, { previous: undefined });
    });

    it('hands the component state of one snapshot to the next', async () => {
        const reuse: SnapshotReuse = {};

        await createSnapshot({ adminRoot: FIXTURE_ROOT, ref: 'WORKTREE', commit: null, reuse });

        expect(reuse.components).toBe(componentState);

        await createSnapshot({ adminRoot: FIXTURE_ROOT, ref: 'WORKTREE', commit: null, reuse });

        expect(jest.mocked(extractComponents).mock.lastCall?.[1]).toEqual({ previous: componentState });
    });

    it('creates an analysis context for the admin root when none is given', async () => {
        await createSnapshot({ adminRoot: FIXTURE_ROOT, ref: 'WORKTREE', commit: null });

        const [context] = jest.mocked(extractShopwareObject).mock.calls[0];

        expect(context.adminRoot).toBe(FIXTURE_ROOT);
        expect(context.program.getSourceFile(path.join(FIXTURE_ROOT, 'src/core/shopware.ts'))).toBeDefined();
    });
});
