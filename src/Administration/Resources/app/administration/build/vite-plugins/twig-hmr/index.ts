/**
 * @sw-package framework
 * @private - Only to be used by Shopware directly.
 */
import { parse } from '@babel/parser';
import MagicString from 'magic-string';
import path from 'path';
import type { Plugin } from 'vite';

type AstNode = { type: string; start: number; end: number } & Record<string, unknown>;

type TemplateImport = {
    source: string;
    local: string;
};

type ComponentAssociation = {
    componentName: string;
    overrideId: string;
};

type TemplateOverride = {
    source: string;
    componentName: string;
    hmrId: string;
};

function isAstNode(value: unknown): value is AstNode {
    return (
        typeof value === 'object' &&
        value !== null &&
        'type' in value &&
        typeof value.type === 'string' &&
        'start' in value &&
        typeof value.start === 'number' &&
        'end' in value &&
        typeof value.end === 'number'
    );
}

function visit(value: unknown, callback: (node: AstNode) => void): void {
    if (Array.isArray(value)) {
        value.forEach((child) => visit(child, callback));
        return;
    }

    if (!isAstNode(value)) {
        return;
    }

    callback(value);
    Object.values(value).forEach((child) => visit(child, callback));
}

function getStaticString(node: unknown): string | null {
    if (!isAstNode(node)) {
        return null;
    }

    if ((node.type === 'StringLiteral' || node.type === 'Literal') && typeof node.value === 'string') {
        return node.value;
    }

    return null;
}

function getIdentifier(node: unknown): string | null {
    if (!isAstNode(node) || node.type !== 'Identifier' || typeof node.name !== 'string') {
        return null;
    }

    return node.name;
}

function isShopwareComponentOverride(node: AstNode): boolean {
    if (node.type !== 'CallExpression' || !isAstNode(node.callee) || node.callee.type !== 'MemberExpression') {
        return false;
    }

    const component = node.callee.object;
    const isComponentApi =
        getIdentifier(component) === 'Component' ||
        (isAstNode(component) &&
            component.type === 'MemberExpression' &&
            getIdentifier(component.property) === 'Component' &&
            getIdentifier(component.object) === 'Shopware');

    return isComponentApi && getIdentifier(node.callee.property) === 'override';
}

function getObjectTemplateProperty(config: AstNode, importedLocal: string): AstNode | null {
    if (config.type !== 'ObjectExpression' || !Array.isArray(config.properties)) {
        return null;
    }

    for (const property of config.properties) {
        if (!isAstNode(property) || property.type !== 'ObjectProperty') {
            continue;
        }

        const key = getIdentifier(property.key) ?? getStaticString(property.key);
        if (key === 'template' && getIdentifier(property.value) === importedLocal) {
            return property;
        }
    }

    return null;
}

function findTemplateConfigObjects(root: AstNode, importedLocal: string): AstNode[] {
    const configs: AstNode[] = [];
    visit(root, (node) => {
        if (getObjectTemplateProperty(node, importedLocal)) {
            configs.push(node);
        }
    });

    return configs;
}

function getDynamicImportSource(node: unknown): string | null {
    let source: string | null = null;
    visit(node, (child) => {
        if (child.type === 'ImportExpression') {
            source = getStaticString(child.source);
        }

        if (
            child.type === 'CallExpression' &&
            isAstNode(child.callee) &&
            child.callee.type === 'Import' &&
            Array.isArray(child.arguments)
        ) {
            source = getStaticString(child.arguments[0]);
        }
    });

    return source;
}

function normalizeModuleId(id: string): string {
    return path.normalize(id.split('?')[0]).replace(/\\/g, '/');
}

function createHmrHandler(source: string, componentName: string, hmrId: string): string {
    return `
if (import.meta.hot) {
    import.meta.hot.accept(${JSON.stringify(source)}, (updatedModule) => {
        const viewAdapter = Shopware.Application.view;
        if (!updatedModule || !viewAdapter) {
            import.meta.hot.invalidate('Shopware could not apply this Twig template update.');
            return;
        }

        void viewAdapter.updateComponentTemplateFromHmr(${JSON.stringify(componentName)}, ${JSON.stringify(hmrId)}, updatedModule.default)
            .then((updated) => {
                if (!updated) import.meta.hot.invalidate('Shopware could not apply this Twig template update.');
            })
            .catch(() => import.meta.hot.invalidate('Shopware could not apply this Twig template update.'));
    });
}
`;
}

/**
 * Automatically connects imported Twig templates to Shopware's component HMR bridge.
 *
 * Supports direct component configs and lazy `Component.override(name, () => import('./component'))`
 * registrations whose imported module contains a static Twig-backed component config.
 * Unsupported dynamic forms keep Vite's default HMR fallback.
 * @private
 */
export default function TwigHmrPlugin(): Plugin {
    const componentAssociations = new Map<string, Map<string, ComponentAssociation>>();
    const associationsByImporter = new Map<string, Array<{ moduleId: string; overrideId: string }>>();

    function clearAssociations(importerId: string): void {
        associationsByImporter.get(importerId)?.forEach(({ moduleId, overrideId }) => {
            const associations = componentAssociations.get(moduleId);
            associations?.delete(overrideId);
            if (associations?.size === 0) {
                componentAssociations.delete(moduleId);
            }
        });

        associationsByImporter.delete(importerId);
    }

    return {
        name: 'shopware-vite-plugin-twig-hmr',
        apply: 'serve',
        enforce: 'pre',

        watchChange(id, change) {
            if (change.event === 'delete') {
                const moduleId = normalizeModuleId(id);
                clearAssociations(moduleId);
                componentAssociations.delete(moduleId);
            }
        },

        async transform(code, id) {
            const moduleId = normalizeModuleId(id);
            clearAssociations(moduleId);

            if (!code.includes('Component.override') && !code.includes('.twig')) {
                return null;
            }

            let ast: AstNode;
            try {
                const parsed: unknown = parse(code, {
                    sourceType: 'module',
                    plugins: [
                        'decorators-legacy',
                        'importAttributes',
                        'jsx',
                        'typescript',
                    ],
                });
                if (!isAstNode(parsed)) return null;
                ast = parsed;
            } catch {
                if (code.includes('Component.override') && code.includes('import(')) {
                    this.warn(
                        `Could not inspect component override imports in ${id}; Vite will use its default HMR behavior.`,
                    );
                }
                return null;
            }

            const imports = new Map<string, TemplateImport>();
            visit(ast, (node) => {
                if (node.type !== 'ImportDeclaration' || !isAstNode(node.source)) {
                    return;
                }

                const source = getStaticString(node.source);
                if (!source?.endsWith('.twig') || !Array.isArray(node.specifiers)) {
                    return;
                }

                node.specifiers.forEach((specifier) => {
                    if (!isAstNode(specifier) || specifier.type !== 'ImportDefaultSpecifier') {
                        return;
                    }

                    const local = getIdentifier(specifier.local);
                    if (local) {
                        imports.set(local, { source, local });
                    }
                });
            });

            const magicString = new MagicString(code);
            const overrides: TemplateOverride[] = [];
            const directConfigAssociations = new Map<number, ComponentAssociation[]>();
            const dynamicImports: Array<{ source: string; association: ComponentAssociation }> = [];

            visit(ast, (node) => {
                if (!isShopwareComponentOverride(node) || !Array.isArray(node.arguments)) {
                    return;
                }

                const args = node.arguments as unknown[];
                const componentName = getStaticString(args[0]);
                if (!componentName) {
                    return;
                }

                const overrideId = `${moduleId}#override=${node.start}`;
                const association = { componentName, overrideId };
                const config = args[1];

                if (isAstNode(config) && config.type === 'ObjectExpression') {
                    imports.forEach((templateImport) => {
                        if (getObjectTemplateProperty(config, templateImport.local)) {
                            directConfigAssociations.set(config.start, [association]);
                        }
                    });
                    return;
                }

                const source = getDynamicImportSource(config);
                if (source) {
                    dynamicImports.push({ source, association });
                }
            });

            const currentAssociations: Array<{ moduleId: string; overrideId: string }> = [];

            for (const { source, association } of dynamicImports) {
                const resolved = await this.resolve(source, id, { skipSelf: true });
                if (!resolved) {
                    continue;
                }

                const resolvedId = normalizeModuleId(resolved.id);
                const associations = componentAssociations.get(resolvedId) ?? new Map<string, ComponentAssociation>();
                associations.set(association.overrideId, association);
                componentAssociations.set(resolvedId, associations);
                currentAssociations.push({ moduleId: resolvedId, overrideId: association.overrideId });
            }

            if (currentAssociations.length > 0) {
                associationsByImporter.set(moduleId, currentAssociations);
            }

            const associatedComponents = Array.from(componentAssociations.get(moduleId)?.values() ?? []);

            for (const [local, templateImport] of imports) {
                const templatePath = path.resolve(path.dirname(id), templateImport.source).replace(/\\/g, '/');
                const configs = findTemplateConfigObjects(ast, local);

                for (const config of configs) {
                    const directAssociations = directConfigAssociations.get(config.start) ?? [];
                    const matchedAssociations = directAssociations.length > 0 ? directAssociations : associatedComponents;
                    if (matchedAssociations.length === 0) {
                        continue;
                    }

                    const hmrId = `${templatePath}#config=${config.start}`;
                    if (
                        !(config.properties as unknown[]).some(
                            (property) => isAstNode(property) && getIdentifier(property.key) === '__shopwareTwigHmrId',
                        )
                    ) {
                        magicString.appendLeft(config.start + 1, `__shopwareTwigHmrId: ${JSON.stringify(hmrId)}, `);
                    }

                    matchedAssociations.forEach(({ componentName }) => {
                        overrides.push({ source: templateImport.source, componentName, hmrId });
                    });
                }
            }

            if (overrides.length === 0) {
                return null;
            }

            const handlers = new Set(
                overrides.map(({ source, componentName, hmrId }) => createHmrHandler(source, componentName, hmrId)),
            );

            magicString.append(`\n${Array.from(handlers).join('\n')}`);

            return {
                code: magicString.toString(),
                map: magicString.generateMap({ hires: true }),
            };
        },
    };
}
