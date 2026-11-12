/**
 * @sw-package framework
 */
import fs from 'node:fs';
import path from 'node:path';
import { isFixturePath } from '../public-api-source-files';
import { byCodePoint } from './code-point-order';

/**
 * @private
 *
 * The files of one Administration checkout that can declare public API. `files` holds paths below the
 * Administration root with forward slashes, for example `src/app/component/index.ts`.
 */
export interface SourceTree {
    files: readonly string[];
    has(file: string): boolean;
    read(file: string): string;
}

const SOURCE_FILE = /\.(?:js|ts|vue|html\.twig)$/;

const NOT_SHIPPED = /\.(?:spec\.(?:js|ts)|d\.ts)$/;

/**
 * @private
 *
 * The tree of `files`, whatever order they are listed in.
 */
export function sourceTreeOf(files: readonly string[], read: (file: string) => string): SourceTree {
    const known = new Set(files);
    const contents = new Map<string, string>();

    return {
        files: [...known].sort(byCodePoint),
        has: (file) => known.has(file),
        read(file) {
            let content = contents.get(file);

            if (content === undefined) {
                content = read(file);
                contents.set(file, content);
            }

            return content;
        },
    };
}

/**
 * @private
 *
 * Reads the source files below `<administrationRoot>/src`, without specs, mocks and fixtures.
 */
export function readSourceTree(administrationRoot: string): SourceTree {
    const files = fs
        .readdirSync(path.join(administrationRoot, 'src'), { recursive: true, encoding: 'utf8' })
        .map((file) => `src/${file.split(path.sep).join('/')}`)
        .filter((file) => SOURCE_FILE.test(file) && !NOT_SHIPPED.test(file) && !isFixturePath(file));

    return sourceTreeOf(files, (file) => fs.readFileSync(path.join(administrationRoot, file), 'utf8'));
}
