import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { ownEntries, ownFiles } from './own-entries';

describe('ownEntries', () => {
    it('leaves out a mini-module at any depth of the component directory, which keeps its place', () => {
        // ARRANGE
        const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'own-entries-'));
        [
            'index.ts',
            'sw-x.html.twig',
            'sw-x-child/private.ts',
            'sw-x-child/src/sw-x-child.js',
            'sw-x-child/tests/.gitkeep',
            'sw-x-plain/index.js',
            'components/sw-x-grid/private.ts',
            'components/sw-x-grid/src/sw-x-grid.js',
            'components/sw-x-grid/tests/.gitkeep',
            'components/sw-x-modal/index.js',
        ].forEach((file) => {
            fs.mkdirSync(path.dirname(path.join(directory, file)), { recursive: true });
            fs.writeFileSync(path.join(directory, file), '');
        });

        // ACT
        const entries = ownEntries(directory);
        const files = ownFiles(directory);

        // ASSERT
        expect(entries.sort((a, b) => a.name.localeCompare(b.name))).toEqual([
            { name: 'components', holdsModule: true },
            { name: 'index.ts', holdsModule: false },
            { name: 'sw-x-plain', holdsModule: false },
            { name: 'sw-x.html.twig', holdsModule: false },
        ]);
        expect(files.sort()).toEqual([
            'components/sw-x-modal/index.js',
            'index.ts',
            'sw-x-plain/index.js',
            'sw-x.html.twig',
        ]);
        fs.rmSync(directory, { recursive: true });
    });
});
