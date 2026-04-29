/**
 * @private
 * @sw-package discovery
 *
 * Generates component-imports.js for the Storefront admin-modules. The output
 * powers the local wrapTestComponent helper in tests. ts-morph and other deps
 * are resolved from the Administration's node_modules (NODE_PATH is set by the
 * unit-setup npm script).
 */
import { ArrowFunction, CallExpression, Project, SourceFile, ts } from "ts-morph";
import * as path from "path";
import * as fs from "fs";

const project = new Project({
    skipAddingFilesFromTsConfig: true,
});

project.addSourceFilesAtPaths([
    "src/**/*{.js,.ts}",
    "!src/**/*{.spec.js,.spec.vue2.js,.d.ts,.types.ts}",
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
        `Component.${functionString}`,
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

// Output paths must be resolvable from the wrapTestComponent helper's location
// at runtime (no moduleNameMapper available — admin's jest config knows nothing
// about this package). We emit paths relative to that helper.
const helperDir = path.resolve(__dirname, '../../test/_helper_/componentWrapper');

function buildRelativePathForSourceFile(sourceFile: SourceFile): string {
    const rel = path.relative(helperDir, sourceFile.getDirectoryPath());
    // path.relative omits the leading "./", which is required for Node to treat
    // the result as a relative import rather than a package name.
    return rel.startsWith('.') ? rel : `./${rel}`;
}

function buildAliasPathForArrowFunctionImport(arrowFunction: ArrowFunction, sourceFile: SourceFile): string {
    const importPath = arrowFunction
        .getDescendantsOfKind(ts.SyntaxKind.StringLiteral)[0]
        .getText()
        .replace(/['"]/g, '');

    if (importPath.includes('./')) {
        return path.join(buildRelativePathForSourceFile(sourceFile), importPath);
    }
    return importPath;
}

function processComponentRegisterCall(sourceFile: SourceFile, call: CallExpression<ts.CallExpression>): void {
    const componentName = getComponentNameFromArgumentNumber(call, 1);
    throwIfComponentIsAlreadyRegistered(componentName, sourceFile);

    const secondArgument = call.getArguments()[1];

    if (secondArgument.getKind() === ts.SyntaxKind.ArrowFunction) {
        const body = (secondArgument as ArrowFunction).getBody();
        let arrowFunctionImportsComponent = false;

        if (body) {
            const firstStatement = body.getFirstChild();
            if (!firstStatement) {
                return;
            }
            if (firstStatement.getText() === 'import') {
                arrowFunctionImportsComponent = true;
            }
        } else {
            return;
        }

        const aliasPath = arrowFunctionImportsComponent
            ? buildAliasPathForArrowFunctionImport(secondArgument as ArrowFunction, sourceFile)
            : buildRelativePathForSourceFile(sourceFile);

        componentImportMap[componentName] = {
            p: aliasPath,
            r: true,
        };
        return;
    }

    if (secondArgument.getKind() === ts.SyntaxKind.ObjectLiteralExpression) {
        componentImportMap[componentName] = {
            p: buildRelativePathForSourceFile(sourceFile),
            r: false,
        };
    }
}

function processComponentExtendCall(sourceFile: SourceFile, call: CallExpression<ts.CallExpression>): void {
    const componentName = getComponentNameFromArgumentNumber(call, 1);
    const extendedComponentName = getComponentNameFromArgumentNumber(call, 2);

    throwIfComponentIsAlreadyRegistered(componentName, sourceFile);

    const thirdArgument = call.getArguments()[2];

    if (thirdArgument.getKind() === ts.SyntaxKind.ArrowFunction) {
        const importPath = thirdArgument
            .getDescendantsOfKind(ts.SyntaxKind.StringLiteral)[0]
            .getText()
            .replace(/['"]/g, '');

        const aliasPath = importPath.includes('./')
            ? path.join(buildRelativePathForSourceFile(sourceFile), importPath)
            : importPath;

        componentImportMap[componentName] = {
            p: aliasPath,
            r: false,
            en: extendedComponentName,
            e: true,
        };
        return;
    }

    if (thirdArgument.getKind() === ts.SyntaxKind.ObjectLiteralExpression) {
        componentImportMap[componentName] = {
            p: buildRelativePathForSourceFile(sourceFile),
            r: false,
            en: extendedComponentName,
            e: false,
        };
    }
}

const componentImportMap: { [key: string]: componentInfo } = {};
const sourceFiles = project.getSourceFiles();

for (const sourceFile of sourceFiles) {
    sourceFile.getDescendantsOfKind(ts.SyntaxKind.CallExpression).forEach((call) => {
        if (isComponentCall(call, 'register')) {
            processComponentRegisterCall(sourceFile, call);
        }
        if (isComponentCall(call, 'extend')) {
            processComponentExtendCall(sourceFile, call);
        }
    });
}

const filestring = `/* eslint-disable */\n\nexport default ${JSON.stringify(componentImportMap)};\n`;
fs.writeFileSync(
    path.join(__dirname, '/../../test/_helper_/componentWrapper/component-imports.js'),
    filestring,
);

// eslint-disable-next-line no-console
console.log(`Generated component-imports.js with ${Object.keys(componentImportMap).length} entries.`);
