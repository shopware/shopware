import fs from 'fs';

const OLD_BLOCK_START_REGEX = /\{%\s*block\s+([^%\s\}]+)\s*%\}/g;
// `(?![-\w])` keeps `<sw-block-field>`, `<sw-block-parent>` and `<sw-block-override>` out: they are
// unrelated components, and `[^>]` spans newlines, so without the guard a multi-line
// `<sw-block-field :name="x">` reads as a block declaration.
// The leading `\s` is what makes the attribute static: a bound `:name` or `v-bind:name` carries no
// literal block name, and `<sw-block>` in an SFC only accepts a static one anyway.
const NEW_BLOCK_START_REGEX = /<sw-block(?![-\w])[^>]*\s(?:name|extends)="([^"]+)"/g;
export function extractBlocks(filesPath: string[]) {
    return filesPath.reduce(function (listOfBlocks, filePath) {
        const code = fs.readFileSync(filePath, 'utf8');
        let match;
        while ((match = OLD_BLOCK_START_REGEX.exec(code)) !== null) {
            listOfBlocks.push(match[1]);
        }
        while ((match = NEW_BLOCK_START_REGEX.exec(code)) !== null) {
            listOfBlocks.push(match[1]);
        }
        return listOfBlocks;
    }, [] as string[]);
}
