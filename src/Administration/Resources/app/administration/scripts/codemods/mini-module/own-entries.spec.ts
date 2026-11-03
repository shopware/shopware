import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { ownEntries } from './own-entries';

describe('ownEntries', () => {
    it('leaves out a mini-module inside the component directory, which keeps its place', () => {
        // ARRANGE
        const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'own-entries-'));
        [
            'index.ts',
            'sw-x.html.twig',
            'sw-x-child/private.ts',
            'sw-x-child/src/sw-x-child.js',
            'sw-x-child/tests/.gitkeep',
            'sw-x-plain/index.js',
        ].forEach((file) => {
            fs.mkdirSync(path.dirname(path.join(directory, file)), { recursive: true });
            fs.writeFileSync(path.join(directory, file), '');
        });

        // ACT
        const entries = ownEntries(directory);

        // ASSERT
        expect(entries.sort()).toEqual([
            'index.ts',
            'sw-x-plain',
            'sw-x.html.twig',
        ]);
        fs.rmSync(directory, { recursive: true });
    });
});
