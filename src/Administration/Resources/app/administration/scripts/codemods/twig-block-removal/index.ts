import { globSync } from 'glob';
import fs from 'fs';
import readlinePromises from 'node:readline/promises';
import { ESLint } from 'eslint';

interface TerminalOptions {
    shouldMoveConditionals: boolean;
    shouldMoveSlots: boolean;
    isOverriding: boolean;
}

const BLOCK_START_REGEX = /\{%\s*block\s+([^%\s\}]+)\s*%\}/g;
const BLOCK_END_REGEX = /\{%\s*endblock\s*%\}/g;
const BLOCK_PARENT_REGEX = /\{[\{|%]\s*parent\(?\)?\s*[\}|%]\}/g;
const BLOCK_EXTENDS_REGEX = /\{%\s*extends\s+'[^']+'\s*%\}/g;
const DEPRECATING_COMMENT = '<!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->';

/**
 * This script replaces the usage of the block and block-parent twig tags in twig files.
 * It replaces them with the sw-block and the sw-block-parent components.
 * The script will ask for user input to decide if the slots and v-if conditions should be moved to the blocks.
 *
 * The script may receive a path as an argument to specify the target directory or file to modify.
 * If no path is provided, the script will modify all .html.twig files in the project.
 */
async function main() {
    const twigFiles = getTwigFiles();
    let count = 0;

    const options = await getOptions();

    // Loop through all .html.twig files and modify them
    for (const twigFile of twigFiles) {
        const inputCode = fs.readFileSync(twigFile, 'utf8');
        let outputCode = replaceBlocks(inputCode);

        if (outputCode && outputCode !== inputCode) {
            count++;
            fs.writeFileSync(twigFile, outputCode);
            await lintFile(twigFile, options);
            console.log('Modified file: ', twigFile);
        }
    }

    console.log(`Finished modifying ${count} file${count > 1 ? 's' : ''}.`);
}

function getTwigFiles() {
    let filesMatchExpression = '**/*.html.twig';
    const targetPath = process.argv[2];
    if (targetPath) {
        if (!fs.existsSync(targetPath)) {
            console.error('Target directory or file does not exist.');
            process.exit(1);
        }
        if (targetPath.endsWith('.html.twig')) {
            filesMatchExpression = targetPath;
        } else {
            filesMatchExpression = `${targetPath}/**/*.html.twig`;
        }
    }

    return globSync(filesMatchExpression, { ignore: 'node_modules/**' });
}

async function getOptions(): Promise<TerminalOptions> {
    const terminalInterface = readlinePromises.createInterface({
        input: process.stdin,
        output: process.stdout
    });

    let givenAnswer = await terminalInterface.question('Is it a plugin and/or overriding defined blocks? (y/n) \n');
    const isOverriding = givenAnswer === 'y';

    givenAnswer = await terminalInterface.question('Do you want to move conditionals to the blocks elements? (y/n) \n');
    const shouldMoveConditionals = givenAnswer === 'y';

    givenAnswer = await terminalInterface.question('Do you want to move slots to the blocks? (y/n) \n');
    const shouldMoveSlots = givenAnswer === 'y';

    terminalInterface.close();
    return { shouldMoveConditionals, shouldMoveSlots, isOverriding }
}

function replaceBlocks(code: string) {
    if (!BLOCK_START_REGEX.test(code)) {
        return null;
    }
    return code
        .split('\n')
        .filter((line) => !BLOCK_EXTENDS_REGEX.test(line))
        .filter((line) => line.trim() !== DEPRECATING_COMMENT)
        .map((line) => line.replace(DEPRECATING_COMMENT, ''))
        .map((line) => line.replace(BLOCK_START_REGEX, '<sw-block name="$1" :data="$dataScope">'))
        .map((line) => line.replace(BLOCK_END_REGEX, '</sw-block>'))
        .map((line) => line.replace(BLOCK_PARENT_REGEX, '</sw-block-parent>'))
        .join('\n');
}

async function lintFile(filePath: string, { isOverriding, shouldMoveSlots, shouldMoveConditionals }: TerminalOptions) {
    const eslint = new ESLint({
        fix: true,
        overrideConfig: {
            rules: {
                'sw-core-rules/replace-top-level-blocks-to-extends': isOverriding ? 'error' : 'off',
                'sw-core-rules/move-slots-to-wrap-blocks': shouldMoveSlots ? 'error' : 'off',
                'sw-core-rules/move-v-if-conditions-to-blocks': shouldMoveConditionals ? 'error' : 'off',
                'sw-core-rules/remove-empty-templates': shouldMoveConditionals || shouldMoveSlots ? 'error' : 'off',
            },
        },
    });
    const results = await eslint.lintFiles(filePath);
    await ESLint.outputFixes(results);
}

main();
