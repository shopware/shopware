import fs from 'fs';

const OLD_BLOCK_START_REGEX = /\{%\s*block\s+([^%\s\}]+)\s*%\}/g;
const NEW_BLOCK_START_REGEX = /<sw-block[^>]+(?:name|extends)="([^"]+)"/g;
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
