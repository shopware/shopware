/**
 * @private
 * @package admin
 */

import fs from 'fs';
import path from 'path';
import {
    ArrowFunction,
    CallExpression,
    Expression,
    FunctionLikeDeclaration,
    Node,
    ObjectLiteralExpression,
    Project,
    PropertyAccessExpression,
    SourceFile,
    StringLiteral,
    SyntaxKind,
    ts,
} from 'ts-morph';
import { NodeTypes, parse as parseTemplateAst, type ElementNode, type RootNode } from '@vue/compiler-dom';

type RoleKey = string;
type Privilege = string;

type EntitySchema = Record<
    string,
    {
        properties?: Record<
            string,
            {
                type?: string;
                entity?: string;
            }
        >;
    }
>;

type RoleDefinition = {
    privileges: string[];
    dependencies: string[];
};

type ComponentRequest = {
    file: string;
    line: number;
    sourceEntity: string;
    associations: string[];
    criteriaName?: string;
    guardPrivileges: RoleKey[];
};

type ComponentEdge = {
    file: string;
    guardPrivileges: RoleKey[];
    guardExpressions: string[];
    propGuardExpressions: Array<{
        property: string;
        expression: string;
    }>;
};

type PropGuardMap = Map<string, RoleKey[]>;

type TemplateWalkContext = {
    guardPrivileges: RoleKey[];
    guardExpressions: string[];
    inlineEditExpressions: string[];
};

type GuardSource = {
    privileges: RoleKey[];
    expressions: string[];
};

type StateAssignment = GuardSource & {
    state: string;
    method?: string;
};

export type AclAssociationPrivilegeIssue = {
    file: string;
    line: number;
    role: RoleKey;
    sourceEntity: string;
    association?: string;
    missingPrivilege: Privilege;
    criteriaName?: string;
    guardPrivileges: RoleKey[];
    routePrivileges: RoleKey[];
};

const adminRoot = path.resolve(__dirname, '../..');
const sourceRoot = path.join(adminRoot, 'src');
const schemaPath = path.join(adminRoot, 'test/_mocks_/entity-schema.json');
const globallyRequiredReadPrivileges = new Set([
    'language:read',
    'locale:read',
]);
const knownExistingViolations = new Set([
    'src/module/sw-flow/page/sw-flow-detail/index.js|flow.creator|app_flow_action|app|app:read',
    'src/module/sw-flow/page/sw-flow-detail/index.js|flow.viewer|app_flow_action|app|app:read',
    'src/module/sw-import-export/component/sw-import-export-edit-profile-modal-identifiers/index.js|system.import_export|custom_field_set|customFields|custom_field:read',
    'src/module/sw-import-export/component/sw-import-export-edit-profile-modal-identifiers/index.js|system.import_export|custom_field_set|relations|custom_field_set_relation:read',
    'src/module/sw-import-export/component/sw-import-export-edit-profile-modal-mapping/index.js|system.import_export|custom_field_set|customFields|custom_field:read',
    'src/module/sw-import-export/component/sw-import-export-edit-profile-modal-mapping/index.js|system.import_export|custom_field_set|relations|custom_field_set_relation:read',
    'src/module/sw-settings-measurement/page/sw-settings-measurement/index.js|system.system_config|measurement_system|units|measurement_display_unit:read',
]);

const project = new Project({
    skipAddingFilesFromTsConfig: true,
    compilerOptions: {
        allowJs: true,
        checkJs: false,
    },
});

function relativeToAdmin(filePath: string): string {
    return path.relative(adminRoot, filePath).replaceAll(path.sep, '/');
}

function readEntitySchema(): EntitySchema {
    if (!fs.existsSync(schemaPath)) {
        throw new Error(
            `Missing generated entity schema at ${relativeToAdmin(schemaPath)}. Run composer admin:unit or composer admin:generate-entity-schema-types first.`,
        );
    }

    return JSON.parse(fs.readFileSync(schemaPath, 'utf8')) as EntitySchema;
}

function loadSourceFiles(): SourceFile[] {
    project.addSourceFilesAtPaths([
        path.join(sourceRoot, '**/*{.js,.ts}'),
        `!${path.join(sourceRoot, '**/*{.spec.js,.spec.ts,.d.ts,.types.ts}')}`,
        `!${path.join(sourceRoot, 'meta/**/*')}`,
    ]);

    return project.getSourceFiles().filter((sourceFile) => !sourceFile.getFilePath().includes('/node_modules/'));
}

function isStringLiteral(node: Node | undefined): node is StringLiteral {
    return Node.isStringLiteral(node) || Node.isNoSubstitutionTemplateLiteral(node);
}

function stringValue(node: Node | undefined): string | undefined {
    if (!isStringLiteral(node)) {
        return undefined;
    }

    return node.getLiteralText();
}

function propertyName(node: Node): string | undefined {
    if (Node.isPropertyAssignment(node) || Node.isShorthandPropertyAssignment(node) || Node.isMethodDeclaration(node)) {
        return node.getName();
    }

    return undefined;
}

function objectProperty(object: ObjectLiteralExpression, name: string): Node | undefined {
    return object.getProperties().find((property) => propertyName(property) === name);
}

function propertyInitializerAsObject(object: ObjectLiteralExpression, name: string): ObjectLiteralExpression | undefined {
    const property = objectProperty(object, name);

    if (!Node.isPropertyAssignment(property)) {
        return undefined;
    }

    const initializer = property.getInitializer();

    return Node.isObjectLiteralExpression(initializer) ? initializer : undefined;
}

function propertyInitializerAsArray(object: ObjectLiteralExpression, name: string): Node[] {
    const property = objectProperty(object, name);

    if (!Node.isPropertyAssignment(property)) {
        return [];
    }

    const initializer = property.getInitializer();

    if (!Node.isArrayLiteralExpression(initializer)) {
        return [];
    }

    return initializer.getElements();
}

function getCallExpressionText(call: CallExpression): string {
    return call.getExpression().getText();
}

function getStringArguments(call: CallExpression): string[] {
    return call
        .getArguments()
        .map((argument) => stringValue(argument))
        .filter((argument): argument is string => argument !== undefined);
}

function getFunctionName(functionLike: FunctionLikeDeclaration): string | undefined {
    if (Node.isMethodDeclaration(functionLike)) {
        return functionLike.getName();
    }

    const parent = functionLike.getParent();

    if (Node.isPropertyAssignment(parent) || Node.isMethodDeclaration(parent)) {
        return parent.getName();
    }

    if (Node.isVariableDeclaration(parent)) {
        return parent.getName();
    }

    return undefined;
}

function findRepositoryFactoryEntity(call: CallExpression): string | undefined {
    const expressionText = getCallExpressionText(call);

    if (!expressionText.endsWith('repositoryFactory.create')) {
        return undefined;
    }

    return stringValue(call.getArguments()[0]);
}

function collectRoleDefinitions(sourceFiles: SourceFile[]): Map<RoleKey, RoleDefinition> {
    const roleDefinitions = new Map<RoleKey, RoleDefinition>();

    for (const sourceFile of sourceFiles) {
        if (
            !sourceFile.getFilePath().includes('/module/') ||
            (!sourceFile.getFilePath().endsWith('/acl/index.js') && !sourceFile.getFilePath().endsWith('/acl/index.ts'))
        ) {
            continue;
        }

        for (const call of sourceFile.getDescendantsOfKind(SyntaxKind.CallExpression)) {
            if (!getCallExpressionText(call).endsWith('addPrivilegeMappingEntry')) {
                continue;
            }

            const mapping = call.getArguments()[0];

            if (!Node.isObjectLiteralExpression(mapping)) {
                continue;
            }

            const key = stringValue(
                Node.isPropertyAssignment(objectProperty(mapping, 'key'))
                    ? objectProperty(mapping, 'key')?.getFirstDescendantByKind(SyntaxKind.StringLiteral)
                    : undefined,
            );
            const roles = propertyInitializerAsObject(mapping, 'roles');

            if (!key || !roles) {
                continue;
            }

            for (const roleProperty of roles.getProperties()) {
                const role = propertyName(roleProperty);

                if (!role || (!Node.isPropertyAssignment(roleProperty) && !Node.isMethodDeclaration(roleProperty))) {
                    continue;
                }

                const roleObject = Node.isPropertyAssignment(roleProperty) ? roleProperty.getInitializer() : undefined;

                if (!Node.isObjectLiteralExpression(roleObject)) {
                    continue;
                }

                const roleKey = `${key}.${role}`;
                roleDefinitions.set(roleKey, {
                    privileges: propertyInitializerAsArray(roleObject, 'privileges').flatMap((item) => {
                        const value = stringValue(item);

                        if (value) {
                            return [value];
                        }

                        if (Node.isCallExpression(item) && getCallExpressionText(item).endsWith('getPrivileges')) {
                            return getStringArguments(item);
                        }

                        return [];
                    }),
                    dependencies: propertyInitializerAsArray(roleObject, 'dependencies').flatMap((item) => {
                        const value = stringValue(item);

                        return value ? [value] : [];
                    }),
                });
            }
        }
    }

    return roleDefinitions;
}

function resolveRolePrivileges(roleDefinitions: Map<RoleKey, RoleDefinition>): Map<RoleKey, Set<Privilege>> {
    const resolved = new Map<RoleKey, Set<Privilege>>();

    function resolve(roleKey: RoleKey, seen: Set<RoleKey> = new Set()): Set<Privilege> {
        const cached = resolved.get(roleKey);

        if (cached) {
            return cached;
        }

        if (seen.has(roleKey)) {
            return new Set();
        }

        const definition = roleDefinitions.get(roleKey);
        const privileges = new Set<Privilege>();

        if (!definition) {
            return privileges;
        }

        seen.add(roleKey);

        for (const privilege of definition.privileges) {
            if (privilege.includes(':')) {
                privileges.add(privilege);
                continue;
            }

            for (const inheritedPrivilege of resolve(privilege, new Set(seen))) {
                privileges.add(inheritedPrivilege);
            }
        }

        for (const dependency of definition.dependencies) {
            for (const inheritedPrivilege of resolve(dependency, new Set(seen))) {
                privileges.add(inheritedPrivilege);
            }
        }

        resolved.set(roleKey, privileges);

        return privileges;
    }

    for (const roleKey of roleDefinitions.keys()) {
        resolve(roleKey);
    }

    return resolved;
}

function resolveImportPath(sourceFile: SourceFile, importPath: string): string | undefined {
    const candidates = importPath.startsWith('src/')
        ? [path.join(adminRoot, importPath)]
        : [path.resolve(path.dirname(sourceFile.getFilePath()), importPath)];

    const suffixes = [
        '.ts',
        '.js',
        '/index.ts',
        '/index.js',
        '',
    ];

    for (const candidate of candidates) {
        for (const suffix of suffixes) {
            const filePath = `${candidate}${suffix}`;

            if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
                return path.normalize(filePath);
            }
        }
    }

    return undefined;
}

function collectComponentFiles(sourceFiles: SourceFile[]): Map<string, string> {
    const componentFiles = new Map<string, string>();

    for (const sourceFile of sourceFiles) {
        for (const call of sourceFile.getDescendantsOfKind(SyntaxKind.CallExpression)) {
            if (
                ![
                    'Shopware.Component.register',
                    'Shopware.Component.extend',
                    'Component.register',
                    'Component.extend',
                ].includes(getCallExpressionText(call))
            ) {
                continue;
            }

            const componentName = stringValue(call.getArguments()[0]);
            const importPath = call
                .getDescendantsOfKind(SyntaxKind.ImportKeyword)
                .at(0)
                ?.getParentIfKind(SyntaxKind.CallExpression)
                ?.getArguments()
                .map((argument) => stringValue(argument))
                .find(Boolean);

            if (!componentName || !importPath) {
                continue;
            }

            const componentFile = resolveImportPath(sourceFile, importPath);

            if (componentFile) {
                componentFiles.set(componentName, componentFile);
            }
        }
    }

    return componentFiles;
}

function collectRouteComponentPrivileges(sourceFiles: SourceFile[]): Map<string, Set<RoleKey>> {
    const routePrivileges = new Map<string, Set<RoleKey>>();

    function addRoutePrivilege(componentName: string, privilege: RoleKey): void {
        if (!routePrivileges.has(componentName)) {
            routePrivileges.set(componentName, new Set());
        }

        routePrivileges.get(componentName)?.add(privilege);
    }

    function findMetaPrivilege(route: ObjectLiteralExpression): RoleKey | undefined {
        const meta = propertyInitializerAsObject(route, 'meta');

        if (!meta) {
            return undefined;
        }

        const privilegeProperty = objectProperty(meta, 'privilege');

        if (!Node.isPropertyAssignment(privilegeProperty)) {
            return undefined;
        }

        return stringValue(privilegeProperty.getInitializer());
    }

    function collectComponentNames(route: ObjectLiteralExpression): string[] {
        const componentNames: string[] = [];
        const component = objectProperty(route, 'component');
        const componentName = Node.isPropertyAssignment(component) ? stringValue(component.getInitializer()) : undefined;
        const components = propertyInitializerAsObject(route, 'components');

        if (componentName) {
            componentNames.push(componentName);
        }

        if (components) {
            for (const componentProperty of components.getProperties()) {
                if (!Node.isPropertyAssignment(componentProperty)) {
                    continue;
                }

                const nestedComponentName = stringValue(componentProperty.getInitializer());

                if (nestedComponentName) {
                    componentNames.push(nestedComponentName);
                }
            }
        }

        return componentNames;
    }

    function collectRouteFactoryReturns(sourceFile: SourceFile): Map<string, ObjectLiteralExpression> {
        const routeFactories = new Map<string, ObjectLiteralExpression>();

        for (const functionDeclaration of sourceFile.getFunctions()) {
            const functionName = functionDeclaration.getName();
            const returnStatement = functionDeclaration.getDescendantsOfKind(SyntaxKind.ReturnStatement)[0];
            const expression = returnStatement?.getExpression();

            if (functionName && Node.isObjectLiteralExpression(expression)) {
                routeFactories.set(functionName, expression);
            }
        }

        return routeFactories;
    }

    function resolveRouteChildren(
        route: ObjectLiteralExpression,
        routeFactories: Map<string, ObjectLiteralExpression>,
    ): ObjectLiteralExpression | undefined {
        const childrenProperty = objectProperty(route, 'children');

        if (!Node.isPropertyAssignment(childrenProperty)) {
            return undefined;
        }

        const initializer = childrenProperty.getInitializer();

        if (Node.isObjectLiteralExpression(initializer)) {
            return initializer;
        }

        if (Node.isCallExpression(initializer) && Node.isIdentifier(initializer.getExpression())) {
            return routeFactories.get(initializer.getExpression().getText());
        }

        return undefined;
    }

    function walkRoutes(
        routes: ObjectLiteralExpression,
        routeFactories: Map<string, ObjectLiteralExpression>,
        inheritedPrivilege?: RoleKey,
    ): void {
        for (const routeProperty of routes.getProperties()) {
            if (!Node.isPropertyAssignment(routeProperty)) {
                continue;
            }

            const route = routeProperty.getInitializer();

            if (!Node.isObjectLiteralExpression(route)) {
                continue;
            }

            const privilege = findMetaPrivilege(route) ?? inheritedPrivilege;

            if (privilege) {
                for (const componentName of collectComponentNames(route)) {
                    addRoutePrivilege(componentName, privilege);
                }
            }

            const children = resolveRouteChildren(route, routeFactories);

            if (children) {
                walkRoutes(children, routeFactories, privilege);
            }
        }
    }

    for (const sourceFile of sourceFiles) {
        const routeFactories = collectRouteFactoryReturns(sourceFile);

        for (const call of sourceFile.getDescendantsOfKind(SyntaxKind.CallExpression)) {
            if (!getCallExpressionText(call).endsWith('Module.register')) {
                continue;
            }

            const moduleConfig = call.getArguments()[1];

            if (!Node.isObjectLiteralExpression(moduleConfig)) {
                continue;
            }

            const routes = propertyInitializerAsObject(moduleConfig, 'routes');

            if (routes) {
                walkRoutes(routes, routeFactories);
            }
        }
    }

    return routePrivileges;
}

let templateExpressionCounter = 0;

function preserveLineBreaks(value: string): string {
    return '\n'.repeat(value.split('\n').length - 1);
}

function parseTwigVueTemplate(template: string, templatePath: string): RootNode {
    return parseTemplateAst(
        template.replaceAll(/({#[\s\S]*?#})/g, preserveLineBreaks).replaceAll(/({%[\s\S]*?%})/g, preserveLineBreaks),
        {
            comments: false,
            filename: templatePath,
            onError: () => {},
        },
    );
}

function elementAttributeValue(element: ElementNode, name: string): string | undefined {
    for (const property of element.props) {
        if (property.type === NodeTypes.ATTRIBUTE && property.name === name) {
            return property.value?.content;
        }
    }

    return undefined;
}

function elementDirectiveExpressions(element: ElementNode, name: string, argumentName?: string): string[] {
    const expressions: string[] = [];

    for (const property of element.props) {
        if (property.type !== NodeTypes.DIRECTIVE || property.name !== name) {
            continue;
        }

        if (argumentName && (property.arg?.type !== NodeTypes.SIMPLE_EXPRESSION || property.arg.content !== argumentName)) {
            continue;
        }

        if (property.exp?.loc.source) {
            expressions.push(property.exp.loc.source);
        }
    }

    return expressions;
}

function elementBoundAttributeExpression(element: ElementNode, name: string): string | undefined {
    return elementDirectiveExpressions(element, 'bind', name)[0];
}

function elementBoundAttributeExpressions(element: ElementNode): Array<{ property: string; expression: string }> {
    return element.props.flatMap((property) => {
        if (
            property.type !== NodeTypes.DIRECTIVE ||
            property.name !== 'bind' ||
            property.arg?.type !== NodeTypes.SIMPLE_EXPRESSION ||
            !property.exp?.loc.source
        ) {
            return [];
        }

        return [
            {
                property: property.arg.content,
                expression: property.exp.loc.source,
            },
        ];
    });
}

function parseTemplateExpression(expression: string | undefined): Expression | undefined {
    if (!expression) {
        return undefined;
    }

    try {
        const sourceFile = project.createSourceFile(
            `__acl_template_expression_${templateExpressionCounter}.ts`,
            `const __aclExpression = (${expression});`,
            { overwrite: true },
        );
        templateExpressionCounter += 1;

        return sourceFile.getVariableDeclaration('__aclExpression')?.getInitializer();
    } catch {
        return undefined;
    }
}

function collectAclCanPrivilegesFromTemplateExpression(expression: string | undefined): RoleKey[] {
    const initializer = parseTemplateExpression(expression);

    return initializer ? expressionContainsAclCan(initializer) : [];
}

function collectTruthyAclCanPrivilegesFromTemplateExpression(expression: string | undefined): RoleKey[] {
    const initializer = parseTemplateExpression(expression);

    function collectTruthyPrivileges(node: Node): RoleKey[] {
        if (Node.isParenthesizedExpression(node)) {
            return collectTruthyPrivileges(node.getExpression());
        }

        if (Node.isCallExpression(node) && getCallExpressionText(node).endsWith('acl.can')) {
            return getStringArguments(node);
        }

        if (Node.isBinaryExpression(node) && node.getOperatorToken().getKind() === SyntaxKind.AmpersandAmpersandToken) {
            return [
                ...collectTruthyPrivileges(node.getLeft()),
                ...collectTruthyPrivileges(node.getRight()),
            ];
        }

        return [];
    }

    return initializer ? collectTruthyPrivileges(initializer) : [];
}

function templateExpressionKey(expression: string | undefined): string | undefined {
    const initializer = parseTemplateExpression(expression);

    return initializer ? memberKey(initializer) : undefined;
}

function templateExpressionRequiresTruthyIdentifier(expression: string | undefined, identifier: string): boolean {
    const initializer = parseTemplateExpression(expression);

    function expressionRequiresIdentifier(node: Node): boolean {
        if (Node.isIdentifier(node)) {
            return node.getText() === identifier;
        }

        if (Node.isParenthesizedExpression(node)) {
            return expressionRequiresIdentifier(node.getExpression());
        }

        if (Node.isBinaryExpression(node) && node.getOperatorToken().getKind() === SyntaxKind.AmpersandAmpersandToken) {
            return expressionRequiresIdentifier(node.getLeft()) || expressionRequiresIdentifier(node.getRight());
        }

        return false;
    }

    return initializer ? expressionRequiresIdentifier(initializer) : false;
}

function collectTemplateConditionExpressions(element: ElementNode): string[] {
    return [
        ...elementDirectiveExpressions(element, 'if'),
        ...elementDirectiveExpressions(element, 'else-if'),
        ...elementDirectiveExpressions(element, 'show'),
    ];
}

function collectTemplateGuardPrivileges(element: ElementNode): RoleKey[] {
    return collectTemplateConditionExpressions(element).flatMap(collectTruthyAclCanPrivilegesFromTemplateExpression);
}

function collectTemplateGuardExpressions(element: ElementNode, inlineEditExpressions: string[]): string[] {
    const guardExpressions = collectTemplateConditionExpressions(element);
    const inlineEditGuardExpressions = guardExpressions.some((expression) =>
        templateExpressionRequiresTruthyIdentifier(expression, 'isInlineEdit'),
    )
        ? inlineEditExpressions
        : [];

    return unique([
        ...guardExpressions,
        ...inlineEditGuardExpressions,
    ]).filter((expression) => collectAclCanPrivilegesFromTemplateExpression(expression).length === 0);
}

function collectInlineEditExpressions(element: ElementNode): string[] {
    if (element.tag !== 'sw-data-grid') {
        return [];
    }

    return elementBoundAttributeExpression(element, 'allow-inline-edit')
        ? [elementBoundAttributeExpression(element, 'allow-inline-edit') as string]
        : [];
}

function initialTemplateWalkContext(): TemplateWalkContext {
    return {
        guardPrivileges: [],
        guardExpressions: [],
        inlineEditExpressions: [],
    };
}

function walkTemplateElements(
    root: RootNode | ElementNode,
    callback: (element: ElementNode, context: TemplateWalkContext) => void,
    inheritedContext: TemplateWalkContext = initialTemplateWalkContext(),
): void {
    for (const child of root.children) {
        if (child.type !== NodeTypes.ELEMENT) {
            continue;
        }

        const guardPrivileges = unique([
            ...inheritedContext.guardPrivileges,
            ...collectTemplateGuardPrivileges(child),
        ]);
        const inlineEditExpressions = unique([
            ...inheritedContext.inlineEditExpressions,
            ...collectInlineEditExpressions(child),
        ]);
        const guardExpressions = unique([
            ...inheritedContext.guardExpressions,
            ...collectTemplateGuardExpressions(child, inlineEditExpressions),
        ]);
        const context = {
            guardPrivileges,
            guardExpressions,
            inlineEditExpressions,
        };

        callback(child, context);
        walkTemplateElements(child, callback, context);
    }
}

function methodNameFromExpression(expression: Expression): string | undefined {
    if (Node.isParenthesizedExpression(expression)) {
        return methodNameFromExpression(expression.getExpression());
    }

    if (Node.isIdentifier(expression)) {
        return expression.getText();
    }

    if (Node.isPropertyAccessExpression(expression) && expression.getExpression().getText() === 'this') {
        return expression.getName();
    }

    return undefined;
}

function collectTemplateMethodReferences(expression: string): string[] {
    const initializer = parseTemplateExpression(expression);
    const methodNames = new Set<string>();
    const directMethodName = initializer ? methodNameFromExpression(initializer) : undefined;

    if (directMethodName) {
        methodNames.add(directMethodName);
    }

    for (const call of initializer?.getDescendantsOfKind(SyntaxKind.CallExpression) ?? []) {
        const methodName = methodNameFromExpression(call.getExpression());

        if (methodName) {
            methodNames.add(methodName);
        }
    }

    return [...methodNames];
}

function addGuardSource(guards: Map<string, GuardSource[]>, key: string, source: GuardSource): void {
    guards.set(key, [
        ...(guards.get(key) ?? []),
        source,
    ]);
}

function collectTemplateMethodGuards(sourceFile: SourceFile): Map<string, GuardSource[]> {
    const guards = new Map<string, GuardSource[]>();
    const templateImports = sourceFile
        .getImportDeclarations()
        .map((importDeclaration) => importDeclaration.getModuleSpecifierValue())
        .filter((importPath) => importPath.endsWith('.html.twig'));

    for (const importPath of templateImports) {
        const templatePath = resolveImportPath(sourceFile, importPath);

        if (!templatePath || !fs.existsSync(templatePath)) {
            continue;
        }

        const root = parseTwigVueTemplate(fs.readFileSync(templatePath, 'utf8'), templatePath);

        walkTemplateElements(root, (element, context) => {
            const eventExpressions = elementDirectiveExpressions(element, 'on');

            if (eventExpressions.length === 0) {
                return;
            }

            const disabledPrivileges = collectAclCanPrivilegesFromTemplateExpression(
                elementBoundAttributeExpression(element, 'disabled'),
            );
            const guardSource = {
                privileges: unique([
                    ...context.guardPrivileges,
                    ...disabledPrivileges,
                ]),
                expressions: context.guardExpressions,
            };

            for (const eventExpression of eventExpressions) {
                for (const methodName of collectTemplateMethodReferences(eventExpression)) {
                    addGuardSource(guards, methodName, guardSource);
                }
            }
        });
    }

    return guards;
}

function findEnclosingNamedFunction(node: Node): string | undefined {
    let current: Node | undefined = node;

    while (current) {
        const functionLike =
            Node.isFunctionDeclaration(current) ||
            Node.isFunctionExpression(current) ||
            Node.isArrowFunction(current) ||
            Node.isMethodDeclaration(current) ||
            Node.isConstructorDeclaration(current)
                ? current
                : undefined;
        const functionName = functionLike ? getFunctionName(functionLike) : undefined;

        if (functionName) {
            return functionName;
        }

        current = current.getParent();
    }

    return undefined;
}

function collectMethodCalls(sourceFile: SourceFile): Map<string, Set<string>> {
    const calls = new Map<string, Set<string>>();

    for (const call of sourceFile.getDescendantsOfKind(SyntaxKind.CallExpression)) {
        const caller = findEnclosingNamedFunction(call);
        const callee = methodNameFromExpression(call.getExpression());

        if (!caller || !callee || caller === callee) {
            continue;
        }

        calls.set(caller, calls.get(caller) ?? new Set());
        calls.get(caller)?.add(callee);
    }

    return calls;
}

function collectStateAssignments(sourceFile: SourceFile): StateAssignment[] {
    const assignments: StateAssignment[] = [];

    for (const binary of sourceFile.getDescendantsOfKind(SyntaxKind.BinaryExpression)) {
        if (binary.getOperatorToken().getKind() !== SyntaxKind.EqualsToken) {
            continue;
        }

        const state = memberKey(binary.getLeft());

        if (!state?.startsWith('this.') || binary.getRight().getKind() !== SyntaxKind.TrueKeyword) {
            continue;
        }

        assignments.push({
            state: state.slice('this.'.length),
            method: findEnclosingNamedFunction(binary),
            privileges: collectGuardPrivileges(binary),
            expressions: [],
        });
    }

    return assignments;
}

function addPrivileges(privileges: Map<string, Set<RoleKey>>, key: string, values: RoleKey[]): boolean {
    if (values.length === 0) {
        return false;
    }

    const existing = privileges.get(key) ?? new Set<RoleKey>();
    const previousSize = existing.size;

    values.forEach((value) => existing.add(value));
    privileges.set(key, existing);

    return existing.size !== previousSize;
}

function resolveStateExpressionPrivileges(expression: string, statePrivileges: Map<string, Set<RoleKey>>): RoleKey[] {
    const directPrivileges = collectAclCanPrivilegesFromTemplateExpression(expression);

    if (directPrivileges.length > 0) {
        return directPrivileges;
    }

    const key = templateExpressionKey(expression);

    return key ? [...(statePrivileges.get(key) ?? [])] : [];
}

function resolveSourcePrivileges(source: GuardSource, statePrivileges: Map<string, Set<RoleKey>>): RoleKey[] {
    return unique([
        ...source.privileges,
        ...source.expressions.flatMap((expression) => resolveStateExpressionPrivileges(expression, statePrivileges)),
    ]);
}

function collectStateGuards(sourceFiles: SourceFile[]): Map<string, PropGuardMap> {
    const stateGuards = new Map<string, PropGuardMap>();

    for (const sourceFile of sourceFiles) {
        const templateMethodGuards = collectTemplateMethodGuards(sourceFile);
        const methodCalls = collectMethodCalls(sourceFile);
        const stateAssignments = collectStateAssignments(sourceFile);

        if (templateMethodGuards.size === 0 && stateAssignments.length === 0) {
            continue;
        }

        const methodPrivileges = new Map<string, Set<RoleKey>>();
        const statePrivileges = new Map<string, Set<RoleKey>>();
        let changed = true;

        while (changed) {
            changed = false;

            for (const [
                method,
                sources,
            ] of templateMethodGuards) {
                for (const source of sources) {
                    changed =
                        addPrivileges(methodPrivileges, method, resolveSourcePrivileges(source, statePrivileges)) || changed;
                }
            }

            for (const [
                caller,
                callees,
            ] of methodCalls) {
                for (const callee of callees) {
                    changed = addPrivileges(methodPrivileges, callee, [...(methodPrivileges.get(caller) ?? [])]) || changed;
                }
            }

            for (const assignment of stateAssignments) {
                changed =
                    addPrivileges(statePrivileges, assignment.state, [
                        ...assignment.privileges,
                        ...(assignment.method ? [...(methodPrivileges.get(assignment.method) ?? [])] : []),
                        ...assignment.expressions.flatMap((expression) =>
                            resolveStateExpressionPrivileges(expression, statePrivileges),
                        ),
                    ]) || changed;
            }
        }

        const propGuards: PropGuardMap = new Map();

        for (const [
            state,
            privileges,
        ] of statePrivileges) {
            addPropGuard(propGuards, state, [...privileges]);
        }

        if (propGuards.size > 0) {
            stateGuards.set(sourceFile.getFilePath(), propGuards);
        }
    }

    return stateGuards;
}

function collectTemplateComponentEdges(componentFiles: Map<string, string>): Map<string, ComponentEdge[]> {
    const componentNameByFile = new Map(
        [...componentFiles.entries()].map(
            ([
                componentName,
                file,
            ]) => [
                file,
                componentName,
            ],
        ),
    );
    const edges = new Map<string, ComponentEdge[]>();

    for (const [
        componentName,
        filePath,
    ] of componentFiles) {
        const sourceFile = project.getSourceFile(filePath);

        if (!sourceFile) {
            continue;
        }

        const templateImports = sourceFile
            .getImportDeclarations()
            .map((importDeclaration) => importDeclaration.getModuleSpecifierValue())
            .filter((importPath) => importPath.endsWith('.html.twig'));

        for (const importPath of templateImports) {
            const templatePath = resolveImportPath(sourceFile, importPath);

            if (!templatePath || !fs.existsSync(templatePath)) {
                continue;
            }

            const template = fs.readFileSync(templatePath, 'utf8');
            const root = parseTwigVueTemplate(template, templatePath);
            const childComponents = new Set<string>();

            walkTemplateElements(root, (element, context) => {
                const childComponentFile = componentFiles.get(element.tag);

                if (childComponentFile && componentNameByFile.get(childComponentFile) !== componentName) {
                    childComponents.add(
                        JSON.stringify({
                            file: childComponentFile,
                            guardPrivileges: sorted(context.guardPrivileges),
                            guardExpressions: sorted(context.guardExpressions),
                            propGuardExpressions: sorted(elementBoundAttributeExpressions(element), (a, b) =>
                                `${a.property}:${a.expression}`.localeCompare(`${b.property}:${b.expression}`),
                            ),
                        }),
                    );
                }
            });

            if (childComponents.size > 0) {
                edges.set(
                    filePath,
                    [...childComponents].map((childComponent) => JSON.parse(childComponent) as ComponentEdge),
                );
            }
        }
    }

    return edges;
}

function addPropGuard(propGuards: PropGuardMap, property: string, privileges: RoleKey[]): void {
    const normalizedPrivileges = sorted(unique(privileges));

    if (normalizedPrivileges.length === 0) {
        return;
    }

    propGuards.set(
        property,
        unique([
            ...(propGuards.get(property) ?? []),
            ...normalizedPrivileges,
        ]).sort(),
    );
    propGuards.set(
        `this.${property}`,
        unique([
            ...(propGuards.get(`this.${property}`) ?? []),
            ...normalizedPrivileges,
        ]).sort(),
    );
}

function serializePropGuards(propGuards: PropGuardMap): string {
    return JSON.stringify(
        [...propGuards.entries()]
            .map(
                ([
                    property,
                    privileges,
                ]) => [
                    property,
                    sorted(privileges),
                ],
            )
            .sort((a, b) => a[0].localeCompare(b[0])),
    );
}

function resolveTemplateExpressionPrivileges(
    expression: string,
    propGuards: PropGuardMap,
    stateGuards: PropGuardMap = new Map(),
): RoleKey[] {
    const directPrivileges = collectAclCanPrivilegesFromTemplateExpression(expression);

    if (directPrivileges.length > 0) {
        return directPrivileges;
    }

    const key = templateExpressionKey(expression);

    return key
        ? unique([
              ...(propGuards.get(key) ?? []),
              ...(stateGuards.get(key) ?? []),
          ])
        : [];
}

function resolveEdgeGuardPrivileges(edge: ComponentEdge, propGuards: PropGuardMap, stateGuards: PropGuardMap): RoleKey[] {
    return unique([
        ...edge.guardPrivileges,
        ...edge.guardExpressions.flatMap((expression) =>
            resolveTemplateExpressionPrivileges(expression, propGuards, stateGuards),
        ),
    ]);
}

function resolveChildPropGuards(edge: ComponentEdge, propGuards: PropGuardMap): PropGuardMap {
    const childPropGuards: PropGuardMap = new Map();

    for (const propGuard of edge.propGuardExpressions) {
        addPropGuard(
            childPropGuards,
            propGuard.property,
            resolveTemplateExpressionPrivileges(propGuard.expression, propGuards),
        );
    }

    return childPropGuards;
}

function propagateRoutePrivilegesToFiles(
    componentFiles: Map<string, string>,
    routeComponentPrivileges: Map<string, Set<RoleKey>>,
    componentEdges: Map<string, ComponentEdge[]>,
    stateGuards: Map<string, PropGuardMap>,
): Map<string, Set<RoleKey>> {
    const filePrivileges = new Map<string, Set<RoleKey>>();
    const visitedStates = new Set<string>();
    const queue: Array<{ file: string; privilege: RoleKey; propGuards: PropGuardMap }> = [];

    for (const [
        componentName,
        privileges,
    ] of routeComponentPrivileges) {
        const file = componentFiles.get(componentName);

        if (!file) {
            continue;
        }

        for (const privilege of privileges) {
            queue.push({
                file,
                privilege,
                propGuards: new Map(),
            });
        }
    }

    while (queue.length > 0) {
        const next = queue.shift();

        if (!next) {
            continue;
        }

        const stateKey = `${next.file}|${next.privilege}|${serializePropGuards(next.propGuards)}`;

        if (visitedStates.has(stateKey)) {
            continue;
        }

        visitedStates.add(stateKey);

        const privileges = filePrivileges.get(next.file) ?? new Set<RoleKey>();

        privileges.add(next.privilege);
        filePrivileges.set(next.file, privileges);

        for (const child of componentEdges.get(next.file) ?? []) {
            const edgeGuardPrivileges = resolveEdgeGuardPrivileges(
                child,
                next.propGuards,
                stateGuards.get(next.file) ?? new Map(),
            );
            const childPrivileges = edgeGuardPrivileges.length > 0 ? edgeGuardPrivileges : [next.privilege];
            const childPropGuards = resolveChildPropGuards(child, next.propGuards);

            for (const privilege of childPrivileges) {
                queue.push({
                    file: child.file,
                    privilege,
                    propGuards: childPropGuards,
                });
            }
        }
    }

    return filePrivileges;
}

function memberKey(expression: Expression): string | undefined {
    if (Node.isParenthesizedExpression(expression)) {
        return memberKey(expression.getExpression());
    }

    if (Node.isIdentifier(expression)) {
        return expression.getText();
    }

    if (Node.isPropertyAccessExpression(expression)) {
        const object = expression.getExpression();

        if (object.getText() === 'this') {
            return `this.${expression.getName()}`;
        }
    }

    return undefined;
}

function getCriteriaReceiver(expression: Expression): { key?: string; prefix?: string } {
    if (Node.isIdentifier(expression) || Node.isPropertyAccessExpression(expression)) {
        return { key: memberKey(expression) };
    }

    if (Node.isCallExpression(expression) && Node.isPropertyAccessExpression(expression.getExpression())) {
        const propertyAccess = expression.getExpression();
        const methodName = propertyAccess.getName();

        if (methodName === 'getAssociation') {
            const nested = getCriteriaReceiver(propertyAccess.getExpression());
            const association = stringValue(expression.getArguments()[0]);

            return {
                key: nested.key,
                prefix: [
                    nested.prefix,
                    association,
                ]
                    .filter(Boolean)
                    .join('.'),
            };
        }

        if (methodName === 'addAssociation') {
            return getCriteriaReceiver(propertyAccess.getExpression());
        }
    }

    return {};
}

function collectAssociationCalls(functionLike: FunctionLikeDeclaration): Map<string, string[]> {
    const associationsByCriteria = new Map<string, string[]>();

    for (const call of functionLike.getDescendantsOfKind(SyntaxKind.CallExpression)) {
        const expression = call.getExpression();

        if (!Node.isPropertyAccessExpression(expression) || expression.getName() !== 'addAssociation') {
            continue;
        }

        const association = stringValue(call.getArguments()[0]);

        if (!association) {
            continue;
        }

        const receiver = getCriteriaReceiver(expression.getExpression());

        if (!receiver.key) {
            continue;
        }

        const associationPath = [
            receiver.prefix,
            association,
        ]
            .filter(Boolean)
            .join('.');
        associationsByCriteria.set(receiver.key, [
            ...(associationsByCriteria.get(receiver.key) ?? []),
            associationPath,
        ]);
    }

    return associationsByCriteria;
}

function collectRepositoryAssignments(functionLike: FunctionLikeDeclaration): Map<string, string> {
    const repositories = new Map<string, string>();
    const functionName = getFunctionName(functionLike);

    for (const call of functionLike.getDescendantsOfKind(SyntaxKind.CallExpression)) {
        const entity = findRepositoryFactoryEntity(call);

        if (!entity) {
            continue;
        }

        const parent = call.getParent();

        if (Node.isVariableDeclaration(parent) && Node.isIdentifier(parent.getNameNode())) {
            repositories.set(parent.getName(), entity);
        }

        if (Node.isReturnStatement(parent) && functionName) {
            repositories.set(`this.${functionName}`, entity);
        }

        if (Node.isBinaryExpression(parent) && parent.getOperatorToken().getKind() === SyntaxKind.EqualsToken) {
            const key = memberKey(parent.getLeft());

            if (key) {
                repositories.set(key, entity);
            }
        }
    }

    return repositories;
}

function collectCriteriaDefinitions(functionLike: FunctionLikeDeclaration): Map<string, string[]> {
    const criteriaNames = new Set<string>();
    const criteriaAssociations = collectAssociationCalls(functionLike);
    const functionName = getFunctionName(functionLike);
    const criteriaDefinitions = new Map<string, string[]>();

    for (const newExpression of functionLike.getDescendantsOfKind(SyntaxKind.NewExpression)) {
        if (newExpression.getExpression().getText() !== 'Criteria') {
            continue;
        }

        const parent = newExpression.getParent();

        if (Node.isVariableDeclaration(parent) && Node.isIdentifier(parent.getNameNode())) {
            criteriaNames.add(parent.getName());
        }

        if (Node.isBinaryExpression(parent) && parent.getOperatorToken().getKind() === SyntaxKind.EqualsToken) {
            const key = memberKey(parent.getLeft());

            if (key) {
                criteriaNames.add(key);
            }
        }
    }

    for (const criteriaName of criteriaNames) {
        criteriaDefinitions.set(criteriaName, criteriaAssociations.get(criteriaName) ?? []);
    }

    for (const returnStatement of functionLike.getDescendantsOfKind(SyntaxKind.ReturnStatement)) {
        const expression = returnStatement.getExpression();

        if (!expression || !functionName) {
            continue;
        }

        const key = memberKey(expression);

        if (key && criteriaDefinitions.has(key)) {
            criteriaDefinitions.set(`this.${functionName}`, criteriaDefinitions.get(key) ?? []);
        }
    }

    return criteriaDefinitions;
}

function findEnclosingFunction(node: Node): FunctionLikeDeclaration | undefined {
    return node.getFirstAncestor(
        (ancestor): ancestor is FunctionLikeDeclaration =>
            Node.isFunctionDeclaration(ancestor) ||
            Node.isFunctionExpression(ancestor) ||
            Node.isArrowFunction(ancestor) ||
            Node.isMethodDeclaration(ancestor) ||
            Node.isConstructorDeclaration(ancestor),
    );
}

function expressionContainsAclCan(expression: Node): RoleKey[] {
    return expression.getDescendantsOfKind(SyntaxKind.CallExpression).flatMap((call) => {
        if (!getCallExpressionText(call).endsWith('acl.can')) {
            return [];
        }

        return getStringArguments(call);
    });
}

function statementAlwaysExits(statement: Node): boolean {
    if (
        Node.isReturnStatement(statement) ||
        Node.isThrowStatement(statement) ||
        Node.isContinueStatement(statement) ||
        Node.isBreakStatement(statement)
    ) {
        return true;
    }

    if (Node.isBlock(statement)) {
        return statement.getStatements().some(statementAlwaysExits);
    }

    return false;
}

function collectGuardPrivileges(node: Node): RoleKey[] {
    const guards = new Set<RoleKey>();
    let current: Node | undefined = node;

    while (current) {
        const parent = current.getParent();

        if (Node.isIfStatement(parent)) {
            const condition = parent.getExpression();
            const conditionPrivileges = expressionContainsAclCan(condition);
            const isInThen =
                parent.getThenStatement() === current ||
                parent.getThenStatement().containsRange(current.getStart(), current.getEnd());
            const isInElse =
                parent.getElseStatement() === current ||
                parent.getElseStatement()?.containsRange(current.getStart(), current.getEnd());
            const isNegatedCondition =
                Node.isPrefixUnaryExpression(condition) && condition.getOperatorToken() === SyntaxKind.ExclamationToken;

            if (conditionPrivileges.length > 0 && ((isInThen && !isNegatedCondition) || (isInElse && isNegatedCondition))) {
                conditionPrivileges.forEach((privilege) => guards.add(privilege));
            }
        }

        current = parent;
    }

    const functionLike = findEnclosingFunction(node);
    const functionBody = functionLike && 'getBody' in functionLike ? functionLike.getBody() : undefined;

    if (functionBody && Node.isBlock(functionBody)) {
        const statements = functionBody.getStatements();
        const targetStatement = node.getFirstAncestor((ancestor) => statements.includes(ancestor as never));
        const targetIndex = targetStatement ? statements.indexOf(targetStatement as never) : -1;

        if (targetIndex > 0) {
            for (const statement of statements.slice(0, targetIndex)) {
                if (!Node.isIfStatement(statement)) {
                    continue;
                }

                const condition = statement.getExpression();
                const conditionPrivileges = expressionContainsAclCan(condition);
                const isNegatedCondition =
                    Node.isPrefixUnaryExpression(condition) && condition.getOperatorToken() === SyntaxKind.ExclamationToken;

                if (
                    conditionPrivileges.length > 0 &&
                    isNegatedCondition &&
                    statementAlwaysExits(statement.getThenStatement())
                ) {
                    conditionPrivileges.forEach((privilege) => guards.add(privilege));
                }
            }
        }
    }

    return [...guards];
}

function expressionKey(expression: Expression): string | undefined {
    if (Node.isCallExpression(expression)) {
        const callee = expression.getExpression();

        if (Node.isPropertyAccessExpression(callee)) {
            const receiver = memberKey(callee.getExpression());

            return receiver ? `${receiver}.${callee.getName()}()` : undefined;
        }
    }

    return memberKey(expression);
}

function collectComponentRequests(sourceFile: SourceFile): ComponentRequest[] {
    const globalRepositories = new Map<string, string>();
    const globalCriteria = new Map<string, string[]>();
    const requests: ComponentRequest[] = [];

    for (const functionLike of sourceFile
        .getDescendants()
        .filter(
            (node): node is FunctionLikeDeclaration =>
                Node.isFunctionDeclaration(node) ||
                Node.isFunctionExpression(node) ||
                Node.isArrowFunction(node) ||
                Node.isMethodDeclaration(node),
        )) {
        const functionRepositories = collectRepositoryAssignments(functionLike);
        const functionCriteria = collectCriteriaDefinitions(functionLike);

        for (const [
            name,
            entity,
        ] of functionRepositories) {
            if (name.startsWith('this.')) {
                globalRepositories.set(name, entity);
            }
        }

        for (const [
            name,
            associations,
        ] of functionCriteria) {
            if (name.startsWith('this.')) {
                globalCriteria.set(name, associations);
            }
        }

        for (const call of functionLike.getDescendantsOfKind(SyntaxKind.CallExpression)) {
            const expression = call.getExpression();

            if (
                !Node.isPropertyAccessExpression(expression) ||
                ![
                    'get',
                    'search',
                ].includes(expression.getName())
            ) {
                continue;
            }

            const repositoryName = memberKey(expression.getExpression());
            const sourceEntity = repositoryName
                ? (functionRepositories.get(repositoryName) ?? globalRepositories.get(repositoryName))
                : undefined;

            if (!sourceEntity) {
                continue;
            }

            const criteriaExpression = expression.getName() === 'get' ? call.getArguments()[2] : call.getArguments()[0];

            if (!criteriaExpression || !Node.isExpression(criteriaExpression)) {
                continue;
            }

            const criteriaName = expressionKey(criteriaExpression);
            const associations = criteriaName
                ? (functionCriteria.get(criteriaName) ?? globalCriteria.get(criteriaName))
                : undefined;

            if (!associations || associations.length === 0) {
                continue;
            }

            requests.push({
                file: sourceFile.getFilePath(),
                line: call.getStartLineNumber(),
                sourceEntity,
                associations,
                criteriaName,
                guardPrivileges: collectGuardPrivileges(call),
            });
        }
    }

    requests.push(...collectTemplateCriteriaRequests(sourceFile, globalRepositories, globalCriteria));

    return requests;
}

function collectTemplateCriteriaRequests(
    sourceFile: SourceFile,
    globalRepositories: Map<string, string>,
    globalCriteria: Map<string, string[]>,
): ComponentRequest[] {
    const requests: ComponentRequest[] = [];
    const templateImports = sourceFile
        .getImportDeclarations()
        .map((importDeclaration) => importDeclaration.getModuleSpecifierValue())
        .filter((importPath) => importPath.endsWith('.html.twig'));

    for (const importPath of templateImports) {
        const templatePath = resolveImportPath(sourceFile, importPath);

        if (!templatePath || !fs.existsSync(templatePath)) {
            continue;
        }

        const template = fs.readFileSync(templatePath, 'utf8');
        const root = parseTwigVueTemplate(template, templatePath);

        walkTemplateElements(root, (element, context) => {
            const criteriaName =
                elementBoundAttributeExpression(element, 'criteria') ??
                elementBoundAttributeExpression(element, 'rule-filter');
            const entity =
                elementAttributeValue(element, 'entity') ?? (element.tag === 'sw-select-rule-create' ? 'rule' : undefined);
            const repositoryProperty = elementBoundAttributeExpression(element, 'repository');
            const sourceEntity =
                entity ??
                (repositoryProperty
                    ? (globalRepositories.get(`this.${repositoryProperty}`) ?? globalRepositories.get(repositoryProperty))
                    : undefined);
            const associations = criteriaName
                ? (globalCriteria.get(`this.${criteriaName}`) ?? globalCriteria.get(criteriaName))
                : undefined;

            if (!sourceEntity || !associations || associations.length === 0) {
                return;
            }

            requests.push({
                file: sourceFile.getFilePath(),
                line: element.loc.start.line,
                sourceEntity,
                associations,
                criteriaName,
                guardPrivileges: context.guardPrivileges,
            });
        });
    }

    return requests;
}

function resolveAssociationTargets(entitySchema: EntitySchema, sourceEntity: string, associationPath: string): string[] {
    const targets: string[] = [];
    let currentEntity = sourceEntity;

    for (const segment of associationPath.split('.')) {
        const property = entitySchema[currentEntity]?.properties?.[segment];

        if (property?.type !== 'association' || !property.entity) {
            return [];
        }

        currentEntity = property.entity;
        targets.push(currentEntity);
    }

    return targets;
}

function unique<T>(values: T[]): T[] {
    return [...new Set(values)];
}

function sorted<T>(values: T[], compareFn?: (a: T, b: T) => number): T[] {
    return [...values].sort(compareFn);
}

function isKnownExistingViolation(issue: AclAssociationPrivilegeIssue): boolean {
    return knownExistingViolations.has(
        [
            issue.file,
            issue.role,
            issue.sourceEntity,
            issue.association,
            issue.missingPrivilege,
        ].join('|'),
    );
}

export function checkDalAssociationPrivileges(): AclAssociationPrivilegeIssue[] {
    const entitySchema = readEntitySchema();
    const sourceFiles = loadSourceFiles();
    const roleDefinitions = collectRoleDefinitions(sourceFiles);
    const rolePrivileges = resolveRolePrivileges(roleDefinitions);
    const componentFiles = collectComponentFiles(sourceFiles);
    const routeComponentPrivileges = collectRouteComponentPrivileges(sourceFiles);
    const componentEdges = collectTemplateComponentEdges(componentFiles);
    const stateGuards = collectStateGuards(sourceFiles);
    const fileRoutePrivileges = propagateRoutePrivilegesToFiles(
        componentFiles,
        routeComponentPrivileges,
        componentEdges,
        stateGuards,
    );
    const issues: AclAssociationPrivilegeIssue[] = [];

    for (const [
        filePath,
        routePrivileges,
    ] of fileRoutePrivileges) {
        const relativeFilePath = relativeToAdmin(filePath);

        // Shared low-level form components often expose generic entity pickers whose
        // source entity depends on user input or embedding context. Module route
        // propagation is intentionally used for module components, not for these
        // framework-level widgets.
        if (relativeFilePath.startsWith('src/app/component/')) {
            continue;
        }

        const sourceFile = project.getSourceFile(filePath);

        if (!sourceFile) {
            continue;
        }

        for (const request of collectComponentRequests(sourceFile)) {
            const rolesToCheck = request.guardPrivileges.length > 0 ? request.guardPrivileges : [...routePrivileges];
            const requiredPrivilegesByAssociation = new Map<string | undefined, string[]>();

            for (const association of request.associations) {
                requiredPrivilegesByAssociation.set(
                    association,
                    resolveAssociationTargets(entitySchema, request.sourceEntity, association).map(
                        (entity) => `${entity}:read`,
                    ),
                );
            }

            for (const role of rolesToCheck) {
                const privileges = rolePrivileges.get(role);

                if (!privileges) {
                    continue;
                }

                for (const [
                    association,
                    requiredPrivileges,
                ] of requiredPrivilegesByAssociation) {
                    for (const requiredPrivilege of unique(requiredPrivileges)) {
                        if (globallyRequiredReadPrivileges.has(requiredPrivilege) || privileges.has(requiredPrivilege)) {
                            continue;
                        }

                        const issue = {
                            file: relativeToAdmin(request.file),
                            line: request.line,
                            role,
                            sourceEntity: request.sourceEntity,
                            association,
                            missingPrivilege: requiredPrivilege,
                            criteriaName: request.criteriaName,
                            guardPrivileges: request.guardPrivileges,
                            routePrivileges: [...routePrivileges],
                        };

                        if (!isKnownExistingViolation(issue)) {
                            issues.push(issue);
                        }
                    }
                }
            }
        }
    }

    return issues.sort((a, b) =>
        `${a.file}:${a.line}:${a.role}:${a.missingPrivilege}`.localeCompare(
            `${b.file}:${b.line}:${b.role}:${b.missingPrivilege}`,
        ),
    );
}

function formatIssue(issue: AclAssociationPrivilegeIssue): string {
    const association = issue.association ? ` association "${issue.association}"` : '';
    const criteria = issue.criteriaName ? ` via criteria "${issue.criteriaName}"` : '';
    const guards =
        issue.guardPrivileges.length > 0
            ? ` guarded by ${issue.guardPrivileges.join(', ')}`
            : ` reachable from ${issue.routePrivileges.join(', ')}`;

    return `${issue.file}:${issue.line} ${issue.role} misses ${issue.missingPrivilege} for ${issue.sourceEntity}${association}${criteria};${guards}`;
}

if (require.main === module) {
    const issues = checkDalAssociationPrivileges();

    if (issues.length > 0) {
        console.error(`Found ${issues.length} missing Administration DAL association privilege(s):\n`);
        console.error(issues.map(formatIssue).join('\n'));
        process.exitCode = 1;
    } else {
        console.log('Administration DAL association privileges are complete.');
    }
}
