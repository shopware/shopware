import { ArrowFunction, CallExpression, Project, SourceFile, ts} from "ts-morph";
import * as path from "path";
import * as fs from "fs";

const project = new Project({
    skipAddingFilesFromTsConfig: true,
});

// load all the source files from the "src" directory
project.addSourceFilesAtPaths([
    "src/**/*{.js,.ts}",
    "!src/**/*{.spec.js,.spec.vue2.js,.d.ts,.types.ts}",
    "!src/meta/**/*",
]);

interface componentInfo {
    usesShopwareCompatConfig?: boolean,
    path: string,
    needsRegister: boolean,
}
const componentMap: { [componentName: string]: componentInfo } = {};

function isComponentCall(call: CallExpression<ts.CallExpression>, functionString: string): boolean {
    const expression = call.getExpression();

    if (expression === null) {
        return false;
    }

    return [
        `Shopware.Component.${functionString}`,
        `Component.${functionString}`
    ].includes(expression.getText());
}

function getComponentNameFromArgumentNumber(call: CallExpression<ts.CallExpression>, argumentNumber: number): string {
    const argument = call.getArguments()[argumentNumber - 1];

    if (argument === null) {
        throw new Error(`Argument ${argumentNumber} not found in call ${call.getText()}`);
    }

    return argument.getText().replace(/['"]/g, '');
}

function buildRelativePathForSourceFile(sourceFile: SourceFile): string {
    const parentDirectory = sourceFile.getDirectoryPath();

    // Remove everything before and including "/app/administration/" from the parent directory
    return parentDirectory.replace(/.*\/app\/administration\//, '');
}

function buildAliasPathForArrowFunctionImport(arrowFunction: ArrowFunction, sourceFile: SourceFile): string {
    // Get the import path inside the ArrowFunction
    // Shopware.Component.register('sw-xyz', () => import('src/app/xyz'));
    const importPath = arrowFunction
        .getDescendantsOfKind(ts.SyntaxKind.StringLiteral)[0]
        .getText()
        // remove all single and double quotes
        .replace(/['"]/g, '');

    let aliasPath = '';
    if (importPath.includes('./')) {
        const relativePath = buildRelativePathForSourceFile(sourceFile);
        // Combine the relative path with the import path
        aliasPath = path.join(relativePath, importPath);
    } else {
        aliasPath = importPath;
    }

    return aliasPath;
}

function processComponentRegisterCall(sourceFile: SourceFile, call: CallExpression<ts.CallExpression>): void {
    const componentName = getComponentNameFromArgumentNumber(call, 1);

    const secondArgument = call.getArguments()[1];

    // If the secondArgument is a ArrowFunction
    if (secondArgument.getKind() === ts.SyntaxKind.ArrowFunction) {
        // We need to check if the first statement of the arrow function is an "import" statement
        const body = (secondArgument as ArrowFunction).getBody();
        let arrowFunctionImportsComponent = false;

        if (body) {
            // Get the first statement within the arrow function's body
            const firstStatement = body.getFirstChild();
            if(!firstStatement) {
                // If the body is empty, we can't work with it
                return;
            }

            if (firstStatement.getText() === 'import') {
                arrowFunctionImportsComponent = true;
            }
        } else {
            // If the body is empty, we can't work with it
            return;
        }

        // Check if the import path is relative
        let aliasPath = '';
        if (arrowFunctionImportsComponent) {
            aliasPath = buildAliasPathForArrowFunctionImport(secondArgument as ArrowFunction, sourceFile);
        } else {
            aliasPath = buildRelativePathForSourceFile(sourceFile);
        }

        componentMap[componentName] = {
            path: aliasPath,
            needsRegister: true,
        };

        return;
    }

    // If the secondArgument is a ObjectLiteralExpression
    if (secondArgument.getKind() === ts.SyntaxKind.ObjectLiteralExpression) {
        // Get the path of the parent directory of this file
        const path = sourceFile.getDirectoryPath().replace(/.*\/app\/administration\//, '');

        componentMap[componentName] = {
            path: path,
            needsRegister: false,
        };
    }
}

const sourceFiles = project.getSourceFiles();

// Create progress bar
const cliProgress = require('cli-progress');
const pb = new cliProgress.SingleBar({}, cliProgress.Presets.shades_classic);

console.log('Start reading files to get all components... \n')
pb.start(sourceFiles.length, 0);

for (const sourceFile of sourceFiles) {
    // collect all "Shopware.Component.register" or "Shopware.Component.extend" calls inside the file
    sourceFile.getDescendantsOfKind(
        ts.SyntaxKind.CallExpression,
    ).forEach(call => {
            if (isComponentCall(call, 'register')) {
                processComponentRegisterCall(sourceFile, call);
            }
        }
    );

    // increment the progress bar
    pb.increment();
}

// stop the progress bar
pb.stop();

// Iterate through all component and check the usage of compatConfig
console.log('\n')
console.log('Iterate through all components and check if they are using compatConfig...')
console.log('\n')
pb.start(Object.keys(componentMap).length, 0);

const componentMapEntries = Object.entries(componentMap);
for (const [componentName, componentInfo] of componentMapEntries) {
    // Read real component file, try with .js first
    let componentPath = componentInfo.path + '/index.js';
    let file = project.getSourceFile(componentPath);
    // If the file does not exist, try to read the .ts file
    if (!file) {
        componentPath = componentInfo.path + '/index.ts';
        file = project.getSourceFile(componentPath);
    }
    // If both files do not exist, try without the index parameter, first with .js
    if (!file) {
        componentPath = componentInfo.path + '.js';
        file = project.getSourceFile(componentPath);
    }
    // If the file does not exist, try to read the .ts file
    if (!file) {
        componentPath = componentInfo.path + '.ts';
        file = project.getSourceFile(componentPath);
    }

    // Check if file has "compatConfig:" string inside the file
    if (file) {
        const fileContent = file.getFullText()
        componentMap[componentName].usesShopwareCompatConfig = fileContent.includes('compatConfig: Shopware.compatConfig');
    }

    // increment the progress bar
    pb.increment();
}

// stop the progress bar
pb.stop();

// Write output to file
console.log('\n')
console.log('Write output to .vue-compat-components-statistics.json file...')
console.log('\n')

const readableFileStructure = Object.entries(componentMap).reduce<{
    usesShopwareCompatConfig: string[],
    noShopwareCompatConfig: string[],
}>((acc, [componentName, componentInfo]) => {
    if (componentInfo.usesShopwareCompatConfig) {
        acc.usesShopwareCompatConfig.push(componentName);
    } else {
        acc.noShopwareCompatConfig.push(componentName);
    }

    return acc;
}, {
    usesShopwareCompatConfig: [],
    noShopwareCompatConfig: [],
});

const filestring = JSON.stringify(readableFileStructure,  null, "\t");
fs.writeFileSync(path.join(__dirname, '/../../.vue-compat-components-statistics.json'), filestring);
