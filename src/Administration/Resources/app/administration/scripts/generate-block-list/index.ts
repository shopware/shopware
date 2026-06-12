import path from 'path';
import fs from 'fs';
import { globSync } from 'glob';
import { extractBlocks } from './extract-blocks';

const BLOCKS_LIST_FILE = path.join(__dirname, '../../blocks-list.json');

function main() {
    const sourcePath = path.join(__dirname, '../../src');
    const listOfTemplateFiles = globSync(`${sourcePath}/**/*.{html.twig,vue}`);

    const blocks = extractBlocks(listOfTemplateFiles);
    updateBlocksList(blocks);
}

export function updateBlocksList(blocks: string[]) {
    const uniqueBlocks = unique(blocks).sort((a, b) => a.localeCompare(b));
    fs.writeFileSync(BLOCKS_LIST_FILE, JSON.stringify(uniqueBlocks, null, 1));
}

// Most performant way to remove duplicates from an array
// https://codebench.tech/suites/iEolfiHjx8J8iA0WaPHP
function unique(array: string[]) {
    const seen: Record<string, boolean> = {};
    const output: string[] = [];
    array.forEach((item) => {
        if (!seen[item]) {
            seen[item] = true;
            output.push(item);
        }
    });
    return output;
}

main();
