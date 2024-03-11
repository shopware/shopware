/**
 * @private
 * @package admin
 */
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

type componentInfo = {
    p: string, // path to import
    r: boolean, // needs register
    en?: string, // extends component name
    e?: boolean, // needs extends
}

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

function throwIfComponentIsAlreadyRegistered(componentName: string, sourceFile: SourceFile): void {
    if (componentImportMap.hasOwnProperty(componentName)) {
        throw new Error(`Component ${componentName} already exists. Found again in file ${sourceFile.getFilePath()}`);
    }
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

function procsessComponentRegisterCall(sourceFile: SourceFile, call: CallExpression<ts.CallExpression>): void {
    const componentName = getComponentNameFromArgumentNumber(call, 1);
    throwIfComponentIsAlreadyRegistered(componentName, sourceFile);

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

        componentImportMap[componentName] = {
            p: aliasPath,
            r: true,
        };

        return;
    }

    // If the secondArgument is a ObjectLiteralExpression
    if (secondArgument.getKind() === ts.SyntaxKind.ObjectLiteralExpression) {
        // Get the path of the parent directory of this file
        const path = sourceFile.getDirectoryPath().replace(/.*\/app\/administration\//, '');

        componentImportMap[componentName] = {
            p: path,
            r: false,
        };
    }
}

function procsessComponentExtendCall(sourceFile: SourceFile, call: CallExpression<ts.CallExpression>): void {
    const componentName = getComponentNameFromArgumentNumber(call, 1);
    const extendedComponentName = getComponentNameFromArgumentNumber(call, 2);

    throwIfComponentIsAlreadyRegistered(componentName, sourceFile);

    const thirdArgument = call.getArguments()[2];

    // If the thirdArgument is a ArrowFunction
    if (thirdArgument.getKind() === ts.SyntaxKind.ArrowFunction) {
        // Get the import path inside the ArrowFunction
        const importPath = thirdArgument
            .getDescendantsOfKind(ts.SyntaxKind.StringLiteral)[0]
            .getText()
            .replace(/['"]/g, '');

        // Check if the import path is relative
        let aliasPath = '';
        if (importPath.includes('./')) {
            // Get the path of the parent directory of this file
            const parentDirectory = sourceFile.getDirectoryPath();
            // Remove everything before and including "/app/administration/" from the parent directory
            const relativePath = parentDirectory.replace(/.*\/app\/administration\//, '');
            // Combine the relative path with the import path
            aliasPath = path.join(relativePath, importPath);
        } else {
            aliasPath = importPath;
        }

        componentImportMap[componentName] = {
            p: aliasPath,
            r: false,
            en: extendedComponentName,
            e: true,
        };

        return;
    }

    // If the thirdArgument is a ObjectLiteralExpression
    if (thirdArgument.getKind() === ts.SyntaxKind.ObjectLiteralExpression) {
        // Get the path of the parent directory of this file
        const path = sourceFile.getDirectoryPath().replace(/.*\/app\/administration\//, '');

        componentImportMap[componentName] = {
            p: path,
            r: false,
            en: extendedComponentName,
            e: false,
        };
    }
}

const componentImportMap: { [key: string]: componentInfo } = {};
const sourceFiles = project.getSourceFiles();

// Create progress bar
const cliProgress = require('cli-progress');
const pb = new cliProgress.SingleBar({}, cliProgress.Presets.shades_classic);
pb.start(sourceFiles.length, 0);

for (const sourceFile of sourceFiles) {
    // collect all "Shopware.Component.register" or "Shopware.Component.extend" calls inside the file
    sourceFile.getDescendantsOfKind(
        ts.SyntaxKind.CallExpression,
    ).forEach(call => {
            if (isComponentCall(call, 'register')) {
                procsessComponentRegisterCall(sourceFile, call);
            }

            if (isComponentCall(call, 'extend')) {
                procsessComponentExtendCall(sourceFile, call);
            }
        }
    );

    // increment the progress bar
    pb.increment();
}

// stop the progress bar
pb.stop();

// Write output to file
const filestring = `/* eslint-disable */\n\nexport default ${JSON.stringify(componentImportMap)};`
fs.writeFileSync(path.join(__dirname, '/../../test/_helper_/componentWrapper/component-imports.js'), filestring);
