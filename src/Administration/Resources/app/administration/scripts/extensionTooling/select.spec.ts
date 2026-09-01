/**
 * @sw-package framework
 */

import { parseSelection } from './select';
import type { ExtensionToolingProject } from './shared';

function project(name: string, overrides: Partial<ExtensionToolingProject> = {}): ExtensionToolingProject {
    return {
        name,
        technicalNames: [name],
        basePath: `custom/plugins/${name}`,
        vendor: false,
        targets: [
            {
                technicalNames: [name],
                sourcePath: `custom/plugins/${name}/src`,
                adminFolder: `custom/plugins/${name}`,
                bridgePresent: false,
                tsconfig: null,
                eslintConfig: null,
            },
        ],
        ...overrides,
    };
}

describe('scripts/extensionTooling/select parseSelection', () => {
    const projects = [
        project('Alpha'),
        project('Bravo', { vendor: true }),
        project('Charlie'),
        project('Delta', { vendor: true }),
    ];

    it('treats empty input as cancel', () => {
        expect(parseSelection('', projects)).toBe('cancel');
        expect(parseSelection('   ', projects)).toBe('cancel');
    });

    it('selects all with "a" or "all", case-insensitive', () => {
        expect(parseSelection('a', projects)).toBe('all');
        expect(parseSelection('ALL', projects)).toBe('all');
    });

    it('selects only writable (non-vendor) extensions with "w"', () => {
        expect(parseSelection('w', projects)).toEqual({
            names: [
                'Alpha',
                'Charlie',
            ],
        });
    });

    it('errors when "w" is used but every extension is vendor', () => {
        expect(parseSelection('w', [project('Bravo', { vendor: true })])).toEqual({
            error: expect.stringContaining('No writable') as unknown as string,
        });
    });

    it('resolves single numbers and comma lists to names', () => {
        expect(parseSelection('1', projects)).toEqual({ names: ['Alpha'] });
        expect(parseSelection('1,3', projects)).toEqual({
            names: [
                'Alpha',
                'Charlie',
            ],
        });
    });

    it('resolves ranges and de-duplicates into ascending order', () => {
        expect(parseSelection('2-4', projects)).toEqual({
            names: [
                'Bravo',
                'Charlie',
                'Delta',
            ],
        });
        expect(parseSelection('3,1,1', projects)).toEqual({
            names: [
                'Alpha',
                'Charlie',
            ],
        });
    });

    it('rejects out-of-range numbers and malformed ranges', () => {
        expect(parseSelection('0', projects)).toEqual({
            error: expect.stringContaining('out of range') as unknown as string,
        });
        expect(parseSelection('5', projects)).toEqual({
            error: expect.stringContaining('out of range') as unknown as string,
        });
        expect(parseSelection('3-1', projects)).toEqual({
            error: expect.stringContaining('out of bounds') as unknown as string,
        });
        expect(parseSelection('1-9', projects)).toEqual({
            error: expect.stringContaining('out of bounds') as unknown as string,
        });
    });

    it('rejects non-numeric tokens', () => {
        expect(parseSelection('abc', projects)).toEqual({
            error: expect.stringContaining('not a number') as unknown as string,
        });
        expect(parseSelection('1,x', projects)).toEqual({
            error: expect.stringContaining('not a number') as unknown as string,
        });
    });
});
