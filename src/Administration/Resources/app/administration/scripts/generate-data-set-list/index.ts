/**
 * @package admin
 * @private
 */

import fs from 'fs';
import path from 'path';
import cliProgress from 'cli-progress';
import colors from 'picocolors';
import { extractDataSetIds, isDataSetSourceFile } from './extract-data-set-ids';

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

// Get all source files from the specified directory. The file filter and the extraction live in
// `extract-data-set-ids` so this generator and the `src/meta/meta.spec.js` guard cannot drift.
const srcFiles = getAllFiles(path.join(__dirname, '../../src')).filter(isDataSetSourceFile);

console.log(colors.blue('Gathering data sets...\n'));

// Create and start a progress bar
const pb = new cliProgress.SingleBar({}, cliProgress.Presets.shades_classic);
pb.start(srcFiles.length, 0);

const result = srcFiles.flatMap((file) => {
    // Increment the progress bar for each file processed
    pb.increment();

    return extractDataSetIds([file]);
});

// Stop the progress bar
pb.stop();

// Define the output file path for the result
const outputFile = path.join(__dirname, '../../src/meta/data-sets.json');

console.log(colors.blueBright(`\nWriting to ${outputFile}`));
fs.writeFileSync(outputFile, JSON.stringify(result));

console.log(colors.green('\nAll done!'));
