/**
 * @sw-package framework
 */

type ParsedAttribute = {
    name: string,
    value: string | true,
    quoted: boolean,
    hasValue: boolean,
    index: number,
    start: number,
    end: number,
    source: string,
};

type GeneratedAttribute = Pick<ParsedAttribute, 'name' | 'source'>;

class Attributes {
    private readonly attributes: ParsedAttribute[];

    /**
     * Keeps parsed attribute records together with lookup helpers used by normalization.
     */
    constructor(attributes: ParsedAttribute[]) {
        this.attributes = attributes;
    }

    /**
     * Finds the original parsed attribute record so diagnostics can point at its offset.
     */
    get(name: string): ParsedAttribute | undefined {
        return this.attributes.find((attribute) => attribute.name === name);
    }

    /**
     * Exposes all raw records for callers that need to inspect every attribute.
     */
    getAll(): ParsedAttribute[] {
        return this.attributes;
    }

    /**
     * Rebuilds an opening tag attribute string while removing transform-only attributes.
     */
    toSourceWithout(removedAttributeNames: string[]): string {
        const keptAttributes = this.attributes.filter((attribute) => !removedAttributeNames.includes(attribute.name));

        if (keptAttributes.length === 0) {
            return '';
        }

        return ` ${keptAttributes.map((attribute) => attribute.source).join(' ')}`;
    }

    /**
     * Rebuilds an opening tag attribute string and adds a generated fallback language.
     *
     * The Shopware setup transform replaces the original script setup block with generated script content.
     * When the source had no `lang`, the generated script still needs an explicit language so downstream
     * tools consistently route it through their normal script transformer.
     */
    toSourceWithoutEnsuringLanguage(removedAttributeNames: string[], fallbackLanguage: string): string {
        const keptAttributes: (ParsedAttribute | GeneratedAttribute)[] = this.attributes.filter(
            (attribute) => !removedAttributeNames.includes(attribute.name),
        );

        if (!keptAttributes.some((attribute) => attribute.name === 'lang')) {
            const fallbackLanguageAttribute: GeneratedAttribute = {
                name: 'lang',
                source: `lang="${fallbackLanguage}"`,
            };
            const setupIndex = keptAttributes.findIndex((attribute) => attribute.name === 'setup');
            const insertIndex = setupIndex === -1 ? 0 : setupIndex + 1;

            keptAttributes.splice(insertIndex, 0, fallbackLanguageAttribute);
        }

        return ` ${keptAttributes.map((attribute) => attribute.source).join(' ')}`;
    }

    /**
     * Detects bound mode attributes that Vue's descriptor drops from `attrs`.
     */
    hasBoundAttributes(): boolean {
        return this.attributes.some(
            (attribute) =>
                Attributes.isBound(attribute.name, 'sw-component') || Attributes.isBound(attribute.name, 'sw-override'),
        );
    }

    /**
     * Checks whether this setup script is a Shopware transform candidate.
     */
    hasShopwareSetupModeAttribute(): boolean {
        return this.attributes.some((attribute) => attribute.name === 'sw-component' || attribute.name === 'sw-override');
    }

    /**
     * Matches Vue shorthand and long-form binding syntax for a static attribute name.
     */
    static isBound(attributeName: string, staticName: string): boolean {
        return attributeName === `:${staticName}` || attributeName === `v-bind:${staticName}`;
    }
}

module.exports = {
    Attributes,
};

export {
    Attributes,
    type ParsedAttribute,
};
