export const IDENTIFIER_TOKEN_MARKER = Symbol('identifier-token');
export const IDENTIFIER_TEMPLATE_MARKER = Symbol('identifier-template');

export interface IdentifierToken {
    readonly [IDENTIFIER_TOKEN_MARKER]: true;
    readonly preferred: string;
    readonly fallback: readonly string[];
}

export interface IdentifierTemplate {
    readonly [IDENTIFIER_TEMPLATE_MARKER]: true;
    readonly indent?: number;
    getIdentifierTokens(): IdentifierToken[];
    render(resolve: (token: IdentifierToken) => string): string;
}

export type IdentifierTemplateValue = string | number | IdentifierToken | IdentifierTemplate;
export type ScriptLine = string | IdentifierTemplate;
export type ScriptSnippet = string | IdentifierTemplate;

interface IdentOptions {
    fallback?: readonly string[];
}

export function ident(preferred: string, options: IdentOptions = {}): IdentifierToken {
    return Object.freeze({
        [IDENTIFIER_TOKEN_MARKER]: true,
        preferred,
        fallback: Object.freeze([...(options.fallback ?? [])]),
    });
}

export function identTemplate(strings: TemplateStringsArray, ...values: IdentifierTemplateValue[]): IdentifierTemplate {
    const parts: IdentifierTemplateValue[] = [];

    strings.forEach((stringPart, index) => {
        if (stringPart) {
            parts.push(stringPart);
        }

        const value = values[index];
        if (value !== undefined) {
            parts.push(value);
        }
    });

    return createIdentifierTemplate(parts);
}

export function createIdentifierTemplate(parts: IdentifierTemplateValue[], indent?: number): IdentifierTemplate {
    return {
        [IDENTIFIER_TEMPLATE_MARKER]: true,
        indent,
        getIdentifierTokens(): IdentifierToken[] {
            return parts.flatMap((part) => {
                if (isIdentifierToken(part)) {
                    return [part];
                }

                if (isIdentifierTemplate(part)) {
                    return part.getIdentifierTokens();
                }

                return [];
            });
        },
        render(resolve: (token: IdentifierToken) => string): string {
            const rendered = parts
                .map((part) => {
                    if (isIdentifierToken(part)) {
                        return resolve(part);
                    }

                    if (isIdentifierTemplate(part)) {
                        return part.render(resolve);
                    }

                    return String(part);
                })
                .join('');

            return indent === undefined ? rendered : indentRenderedText(rendered, indent);
        },
    };
}

export function indentIdentifierTemplate(template: IdentifierTemplate, spaces: number): IdentifierTemplate {
    return createIdentifierTemplate([template], spaces);
}

export function isIdentifierToken(value: unknown): value is IdentifierToken {
    return (
        typeof value === 'object' &&
        value !== null &&
        (value as { [IDENTIFIER_TOKEN_MARKER]?: true })[IDENTIFIER_TOKEN_MARKER] === true
    );
}

export function isIdentifierTemplate(value: unknown): value is IdentifierTemplate {
    return (
        typeof value === 'object' &&
        value !== null &&
        (value as { [IDENTIFIER_TEMPLATE_MARKER]?: true })[IDENTIFIER_TEMPLATE_MARKER] === true
    );
}

export function renderIdentifierTemplates(lines: ScriptLine[], takenNames: Iterable<string>): string[] {
    const resolver = new IdentifierResolver();

    for (const line of lines) {
        if (!isIdentifierTemplate(line)) {
            continue;
        }

        line.getIdentifierTokens().forEach((token) => resolver.collect(token));
    }

    resolver.finalize(takenNames);

    return lines.map((line) => (isIdentifierTemplate(line) ? line.render((token) => resolver.resolve(token)) : line));
}

class IdentifierResolver {
    private readonly definitions = new Set<IdentifierToken>();

    private readonly names = new Map<IdentifierToken, string>();

    collect(token: IdentifierToken): void {
        this.definitions.add(token);
    }

    finalize(takenNames: Iterable<string>): void {
        const taken = new Set(takenNames);

        for (const token of this.definitions) {
            const name = pickName(token, taken);
            this.names.set(token, name);
            taken.add(name);
        }
    }

    resolve(token: IdentifierToken): string {
        const resolved = this.names.get(token);

        if (!resolved) {
            throw new Error(`Identifier token was not finalized: ${token.preferred}`);
        }

        return resolved;
    }
}

function pickName(token: IdentifierToken, taken: Set<string>): string {
    for (const candidate of [
        token.preferred,
        ...token.fallback,
    ]) {
        if (!taken.has(candidate)) {
            return candidate;
        }
    }

    for (let suffix = 2; ; suffix += 1) {
        const candidate = `${token.preferred}${suffix}`;

        if (!taken.has(candidate)) {
            return candidate;
        }
    }
}

function indentRenderedText(text: string, spaces: number): string {
    const pad = ' '.repeat(spaces);

    return text
        .split('\n')
        .map((line) => (line.trim() === '' ? '' : pad + line))
        .join('\n');
}
