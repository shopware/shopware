/**
 * @sw-package framework
 */

import { STATEMENT_TYPES } from '@babel/types';

describe('build/vue-setup-transform statement types', () => {
    it('keeps known statements aligned with Babel statements', () => {
        const reviewedStatementTypes = [
            'BlockStatement',
            'BreakStatement',
            'ClassDeclaration',
            'ContinueStatement',
            'DebuggerStatement',
            'DeclareClass',
            'DeclareExportAllDeclaration',
            'DeclareExportDeclaration',
            'DeclareFunction',
            'DeclareInterface',
            'DeclareModule',
            'DeclareModuleExports',
            'DeclareOpaqueType',
            'DeclareTypeAlias',
            'DeclareVariable',
            'DoWhileStatement',
            'EmptyStatement',
            'EnumDeclaration',
            'ExportAllDeclaration',
            'ExportDefaultDeclaration',
            'ExportNamedDeclaration',
            'ExpressionStatement',
            'ForInStatement',
            'ForOfStatement',
            'ForStatement',
            'FunctionDeclaration',
            'IfStatement',
            'ImportDeclaration',
            'InterfaceDeclaration',
            'LabeledStatement',
            'OpaqueType',
            'ReturnStatement',
            'SwitchStatement',
            'TSDeclareFunction',
            'TSEnumDeclaration',
            'TSExportAssignment',
            'TSImportEqualsDeclaration',
            'TSInterfaceDeclaration',
            'TSModuleDeclaration',
            'TSNamespaceExportDeclaration',
            'TSTypeAliasDeclaration',
            'ThrowStatement',
            'TryStatement',
            'TypeAlias',
            'VariableDeclaration',
            'WhileStatement',
            'WithStatement',
        ];

        expect(
            [
                ...STATEMENT_TYPES,
            ].sort(),
        ).toEqual(reviewedStatementTypes.sort());
    });
});

