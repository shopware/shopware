import { Project, ts } from "ts-morph";
import fs from 'fs';
import path from 'path';

const noArgumentProvided = process.argv.length === 2;
if (noArgumentProvided) {
    console.error('Please provide a target directory.');

    process.exit(1);
}

function fromPlatformRootDirectory(...paths: string[]) {
    return path.resolve(__dirname, '../../../../../../../../', ...paths);
}

const targetDirectory = process.argv[2];
if (!fs.existsSync(fromPlatformRootDirectory(targetDirectory))) {
    console.error('Target directory does not exist.');

    process.exit(1);
}

const project = new Project({
    skipAddingFilesFromTsConfig: true,
});

console.log(fromPlatformRootDirectory(targetDirectory, '**/*.{js,ts}'))

const nonTestSourceFiles = project.addSourceFilesAtPaths([
    fromPlatformRootDirectory(targetDirectory, '**/*.{js,ts}'),
    fromPlatformRootDirectory(targetDirectory, '!**/*.spec.{js,ts}'),
    fromPlatformRootDirectory(targetDirectory, '!**/*.spec.vue2.{js,ts}'),
]);

nonTestSourceFiles.forEach((sourceFile) => {
    sourceFile.getDescendantsOfKind(ts.SyntaxKind.IfStatement).forEach((ifStatement) => {
        if (ifStatement.wasForgotten()) return;

        const condition = ifStatement.getExpression();
        const isVue3FeatureFlag = [
            'this.feature.isActive(\'VUE3\')',
            'Shopware.Service(\'feature\').isActive(\'VUE3\')'
        ].includes(condition.getText());

        if (isVue3FeatureFlag) {
            const thenStatement = ifStatement.getThenStatement();
            const ifStatementContent = thenStatement.getDescendantStatements();

            // Filter all ifStatementContent when parent is not "ifStatement.getThenStatement()"
            const filteredIfStatementContent = ifStatementContent.filter((statement) => {
                return statement.getParent() === thenStatement;
            });

            ifStatement.replaceWithText(
                filteredIfStatementContent.map((statement) => statement.getText()).join('\n')
            );
        } else if ([
            '!this.feature.isActive(\'VUE3\')',
            '!Shopware.Service(\'feature\').isActive(\'VUE3\')'
        ].includes(condition.getText())) {
            if (ifStatement.getElseStatement()) {
                const elseStatement = ifStatement.getElseStatement();
                if (!elseStatement) return;

                const elseStatementContent = elseStatement.getDescendantStatements();

                // Filter all ifStatementContent when parent is not "ifStatement.getThenStatement()"
                const filteredIfStatementContent = elseStatementContent.filter((statement) => {
                    return statement.getParent() === elseStatement;
                });

                ifStatement.replaceWithText(
                    elseStatementContent.map((statement) => statement.getText()).join('\n')
                );
            } else {
                ifStatement.remove();
            }
        }
    });

    console.log('Saving file: ', sourceFile.getFilePath(), '...');
    sourceFile.saveSync();
});
