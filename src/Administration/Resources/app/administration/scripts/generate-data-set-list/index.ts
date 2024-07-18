/**
 * @package admin
 * @private
 */

import fs from 'fs';
import path from 'path';
// @ts-expect-error - There are no types for this package
import cliProgress from 'cli-progress';
import chalk from 'chalk';

/**
 * Recursively get all files from a directory
 */
function getAllFiles(dirPath: string, arrayOfFiles: string[] = []): string[] {
    const files = fs.readdirSync(dirPath);

    // Ensure arrayOfFiles is initialized
    arrayOfFiles = arrayOfFiles || [];

    files.forEach((file) => {
        if (fs.statSync(`${dirPath}/${file}`).isDirectory()) {
            // If the file is a directory, recursively get its files
            arrayOfFiles = getAllFiles(`${dirPath}/${file}`, arrayOfFiles);
        } else {
            arrayOfFiles.push(path.join(dirPath, '/', file));
        }
    });

    return arrayOfFiles;
}

// Get all HTML Twig template files from the specified directory
const srcFiles = getAllFiles(path.join(__dirname, '../../src')).filter(file => {
    return file.match(/^.*(?<!\.spec|vue2)(?<!\/acl\/index)(?<!\.d)\.(js|ts)$/);
});

console.log(chalk.blue('Gathering data sets...\n'));

// Create and start a progress bar
const pb = new cliProgress.SingleBar({}, cliProgress.Presets.shades_classic);
pb.start(srcFiles.length, 0);

let result: string[] = [];
srcFiles.forEach((file) => {
    // Increment the progress bar for each file processed
    pb.increment();

    const fileContent = fs.readFileSync(file, { encoding: 'utf-8' });
    if (!fileContent.includes('.publishData(')) {
        return;
    }

    // Find all position identifiers in the file and add them to the result
    // May the regex god be with us: https://regex101.com/r/BM083Q/1
    [...fileContent.matchAll(/\.publishData\(\{[^}]*?\bid\s*:\s*['"]([^'"]+)['"]/gm)].map((match) => match[1]).forEach((match) => {
        result.push(match);
    });
})

// Stop the progress bar
pb.stop();

// Define the output file path for the result
const outputFile = path.join(__dirname, '../../src/meta/data-sets.json');

console.log(chalk.blueBright(`\nWriting to ${outputFile}`));
fs.writeFileSync(outputFile, JSON.stringify(result));

console.log(chalk.green('\nAll done!'));
