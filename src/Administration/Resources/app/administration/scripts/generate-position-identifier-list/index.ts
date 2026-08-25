/**
 * @package admin
 * @private
 */

import fs from 'fs';
import path from 'path';
import cliProgress from 'cli-progress';
import colors from 'picocolors';
import { extractPositionIdentifiers, isPositionIdentifierSourceFile } from './extract-position-identifiers';

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

// Get all template files from the specified directory. The file filter and the extraction live in
// `extract-position-identifiers` so this generator and the `src/meta/meta.spec.js` guard cannot drift.
const templateFiles = getAllFiles(path.join(__dirname, '../../src')).filter(isPositionIdentifierSourceFile);

console.log(colors.blue('Gathering position identifiers...\n'));

// Create and start a progress bar
const pb = new cliProgress.SingleBar({}, cliProgress.Presets.shades_classic);
pb.start(templateFiles.length, 0);

const result = templateFiles.flatMap((file) => {
    // Increment the progress bar for each file processed
    pb.increment();

    return extractPositionIdentifiers([file]);
});

// Stop the progress bar
pb.stop();

// Sort the result array to maintain consistent ordering
const sortedPositionIdentifiers = result.sort((a, b) => a.localeCompare(b));

// Define the output file path for the result
const outputFile = path.join(__dirname, '../../src/meta/position-identifiers.json');

console.log(colors.blueBright(`\nWriting to ${outputFile}`));

fs.writeFileSync(outputFile, JSON.stringify(sortedPositionIdentifiers, null, 1));

console.log(colors.green('\nAll done!'));
