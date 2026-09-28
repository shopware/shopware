import { captures } from '../public-api-source-files';

const OLD_BLOCK_START_REGEX = /\{%\s*block\s+([^%\s\}]+)\s*%\}/g;
// `(?![-\w])` keeps `<sw-block-field>`, `<sw-block-parent>` and `<sw-block-override>` out: they are
// unrelated components, and `[^>]` spans newlines, so without the guard a multi-line
// `<sw-block-field :name="x">` reads as a block declaration.
// The leading `\s` is what makes the attribute static: a bound `:name` or `v-bind:name` carries no
// literal block name, and `<sw-block>` in an SFC only accepts a static one anyway.
const NEW_BLOCK_START_REGEX = /<sw-block(?![-\w])[^>]*\s(?:name|extends)="([^"]+)"/g;

export function extractBlocks(code: string): string[] {
    return captures(code, OLD_BLOCK_START_REGEX, NEW_BLOCK_START_REGEX);
}
