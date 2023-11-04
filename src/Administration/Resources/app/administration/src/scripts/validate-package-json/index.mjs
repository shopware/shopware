/**
 * @package admin
 */

import chalk from 'chalk';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const packageJson = JSON.parse(fs.readFileSync(path.join(__dirname, '../../../package.json'), 'utf-8'))
const versionRegex = /(^\^?\d{1,}\.\d{1,}\.\d{1,}$)|(^file:.*$)/; // https://regex101.com/r/07JABk/1
const invalidDependencies = {
    dependencies: [],
    devDependencies: [],
};

Object.keys(packageJson.dependencies).forEach((dependency) => {
    if (packageJson.dependencies[dependency].match(versionRegex)) {
        return;
    }

    invalidDependencies.dependencies.push(dependency);
})

Object.keys(packageJson.devDependencies).forEach((devDependency) => {
    if (packageJson.devDependencies[devDependency].match(versionRegex)) {
        return;
    }

    invalidDependencies.devDependencies.push(devDependency);
})

const invalidDependencyCount = invalidDependencies.dependencies.length + invalidDependencies.devDependencies.length;
if (invalidDependencyCount >= 1) {
    console.log(chalk.red('Using pre-release package versions is prohibited!'));

    invalidDependencies.dependencies.forEach((name) => {
        console.log(chalk.red(`Found dependency "${name}" with version constraint "${packageJson.dependencies[name]}"`));
    })

    invalidDependencies.devDependencies.forEach((name) => {
        console.log(chalk.red(`Found devDependency "${name}" with version constraint "${packageJson.devDependencies[name]}"`));
    })

    throw new Error('See output above!')
}
