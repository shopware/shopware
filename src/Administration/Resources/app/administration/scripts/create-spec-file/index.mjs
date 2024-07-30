/**
 * @package admin
 */

import chalk from 'chalk';
import readline from 'readline';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const getAllFiles = (dirPath, arrayOfFiles) => {
    const files = fs.readdirSync(dirPath);

    arrayOfFiles = arrayOfFiles || [];

    files.forEach((file) => {
        if (fs.statSync(`${dirPath}/${file}`).isDirectory()) {
            arrayOfFiles = getAllFiles(`${dirPath}/${file}`, arrayOfFiles);
        } else {
            arrayOfFiles.push(path.join(dirPath, '/', file));
        }
    });

    return arrayOfFiles.filter((file) => {
        return file.match(/^.*(\.js|\.ts)$/);
    });
};

const findFile = (name) => {
    return allFiles.filter((file) => {
        return file.includes(name);
    });
};

const allFiles = getAllFiles(path.join(__dirname, '../../src'));
const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

console.log(chalk.green('Administration test file generator'));

rl.question(chalk.blueBright('Enter the component name/path you want to test: '), (name) => {
    const files = findFile(name);

    if (files.length === 0) {
        return rl.emit('error', new Error(`No file found for: ${name}`));
    }

    if (files.length === 1) {
        const orgFileName = files[0];
        const fileName = orgFileName.substr(orgFileName.indexOf('app/administration/src') + 19, orgFileName.length);
        let specFileName = '';

        if (fileName.endsWith('index.js') || fileName.endsWith('index.ts')) {
            const regex = /^.*\/(.*)\/index\.(js|ts)/;
            const [_, lastFolder, fileExtension] = fileName.match(regex);

            specFileName = orgFileName.replace(`index.${fileExtension}`, `${lastFolder}.spec.js`)
        } else {
            const regex = /^.*\/(.*)\.(js|ts)/;
            const [_, name, fileExtension] = fileName.match(regex);

            specFileName = orgFileName.replace(`${name}.${fileExtension}`, `${name}.spec.js`)
        }

        if (fs.existsSync(specFileName)) {
            console.log(chalk.red(`Spec file with name "${specFileName}" already exists. Aborting!`));
            return process.exit(1);
        }

        return rl.question(chalk.blueBright(`Do you want to create a test file for ${fileName}? (Y/n) `), (answer) => {
            answer = answer.toUpperCase();

            if (answer === 'Y' || answer === '') {
                let template = fs.readFileSync(path.join(__dirname, 'template/template.spec_js'), 'utf8');
                template = template.replaceAll('<component-path>', fileName);

                fs.writeFileSync(specFileName, template);

                return rl.close();
            }

            rl.close();
        });
    }

    return rl.emit('error', new Error(`No unique file found for: ${name}`));
});

rl.on('close', function () {
    console.log(chalk.green('\nThank you for testing with us!'));
    process.exit(0);
});

rl.on('error', function (err) {
    console.log(chalk.red(err.message));
    process.exit(1);
});
