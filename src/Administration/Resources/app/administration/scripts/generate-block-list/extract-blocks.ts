import fs from 'fs';
import { createSourceFileFilter } from '../public-api-source-files';

const OLD_BLOCK_START_REGEX = /\{%\s*block\s+([^%\s\}]+)\s*%\}/g;
// `(?![-\w])` keeps `<sw-block-field>`, `<sw-block-parent>` and `<sw-block-override>` out: they are
// unrelated components, and `[^>]` spans newlines, so without the guard a multi-line
// `<sw-block-field :name="x">` reads as a block declaration.
// The leading `\s` is what makes the attribute static: a bound `:name` or `v-bind:name` carries no
// literal block name, and `<sw-block>` in an SFC only accepts a static one anyway.
const NEW_BLOCK_START_REGEX = /<sw-block(?![-\w])[^>]*\s(?:name|extends)="([^"]+)"/g;

// A native setup SFC declares its blocks as `<sw-block name="...">` in the `.vue` template, so a
// twig-only scan reports every block of a converted component as removed public API.
export const isBlockTemplateSourceFile = createSourceFileFilter(/^.*\.(html\.twig|vue)$/);

export function extractBlocks(filesPath: string[]): string[] {
    return filesPath.flatMap((filePath) => {
        const code = fs.readFileSync(filePath, 'utf8');

        return [
            OLD_BLOCK_START_REGEX,
            NEW_BLOCK_START_REGEX,
        ].flatMap((pattern) => [...code.matchAll(pattern)].map((match) => match[1]));
    });
}
