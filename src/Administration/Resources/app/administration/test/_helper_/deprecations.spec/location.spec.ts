/**
 * @sw-package framework
 */

import path from 'path';
import { location } from '../deprecations';

describe('test/_helper_/deprecations - location', () => {
    const file = path.resolve(__dirname, '../../a folder/oldFunction.ts');

    it('reads a frame with a function name and a path with spaces', () => {
        expect(location(`    at oldFunction (${file}:13:13)`)).toBe('test/a folder/oldFunction.ts:13:13');
    });

    it('reads a frame without a function name and a path with spaces', () => {
        expect(location(`    at ${file}:13:13`)).toBe('test/a folder/oldFunction.ts:13:13');
    });
});
