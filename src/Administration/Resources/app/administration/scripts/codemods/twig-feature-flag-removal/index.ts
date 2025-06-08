import fs from 'fs';
import path from 'path';
import readline from 'readline';

const terminalInterface = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

function modifyCode(inputCode: string): string {
    const lines = inputCode.split('\n');
    const outputLines: string[] = [];

    let shouldOutputLine = true;
    let isVue3Code = false;
    let isVue2Code = false;
    let isTwigCondition = false;

    for (const line of lines) {
        // Reset for each line
        shouldOutputLine = true;

        /**
         * When line contains '{% if VUE3 %}' we should not output the line
         * and set the flag to true
         */
        if (line.includes('{% if VUE3 %}')) {
            shouldOutputLine = false;
            isVue3Code = true;
            isVue2Code = false;
        }

        /**
         * When line contains '{% if VUE2 %}' or '{% if VUE3 != true %}'
         * we should not output the line and set the flag to true
         */
        if (line.includes('{% if VUE2 %}') || line.includes('{% if VUE3 != true %}')) {
            shouldOutputLine = false;
            isVue3Code = false;
            isVue2Code = true;
        }

        /**
         * When line contains '{% else %}' we should not output the line
         * and set the flag to false
         */
        if (line.includes('{% else %}') && isVue3Code) {
            shouldOutputLine = false;
            isVue3Code = false;
            isVue2Code = true;
        } else if (line.includes('{% else %}') && isVue2Code) {
            shouldOutputLine = false;
            isVue3Code = true;
            isVue2Code = false;
        }

        /**
         * When line contains '{% endif %}' we should not output the line
         * and set the flag to false
         */
        if (line.includes('{% endif %}') && (isVue3Code || isVue2Code)) {
            shouldOutputLine = false;
            isVue3Code = false;
            isVue2Code = false;
        }

        if (isVue2Code) {
            shouldOutputLine = false;
        }

        if (shouldOutputLine) {
            outputLines.push(line);
        }
    }

    return outputLines.join('\n');
}

terminalInterface.question('What is the source directory of the files you want to change? \n', givenSrcPath => {
    const srcPath = path.resolve(givenSrcPath);

    // Read all files recursively ending with .html.twig
    function throughDirectory(directoryPath: string, files: string[] = []) {
        fs.readdirSync(directoryPath).forEach(file => {
            const absolutePath = path.join(directoryPath, file);

            if (fs.statSync(absolutePath).isDirectory()) {
                return throughDirectory(absolutePath, files);
            } else {
                return files.push(absolutePath);
            }
        });

        return files;
    }

    // Filter array with strings and return only .html.twig files
    const twigFiles = throughDirectory(srcPath).filter(file => file.endsWith('.html.twig'));

    // Loop through all .html.twig files and modify them
    for (const twigFile of twigFiles) {
        const inputCode = fs.readFileSync(twigFile, 'utf8');
        const outputCode = modifyCode(inputCode);

        fs.writeFileSync(twigFile, outputCode);
        console.log('Modified file: ', twigFile);
    }

    console.log('Finished modifying files.');
    console.warn(
        'WARNING: It could be that the indentation of the files is not correct anymore. Please check the files manually.'
    );
    terminalInterface.close();
});
