/**
 * @package content
 */
import Vue from 'vue';

const { Utils } = Shopware;

/**
 * @private
 */
export type PageType = {
    name: string,
    icon: string,
    title: string,
    class: string[],
    hideInList: boolean,
};

/**
 * @private
 */
export default class CmsPageTypeService {
    #state = Vue.observable({
        pageTypes: [] as PageType[],
    });

    register(newTypeData: { name: string, icon: string, title?: string, class?: string[], hideInList?: boolean }): void {
        if (this.#state.pageTypes.some((type: PageType) => type.name === newTypeData.name)) {
            throw new Error(`Can't register new Page Type with "${newTypeData.name}" already in use.`);
        }

        const camelCase = Utils.string.camelCase(newTypeData.name);
        const kebabCase = Utils.string.kebabCase(newTypeData.name);
        const newType = {
            name: newTypeData.name,
            icon: newTypeData.icon,
            title: newTypeData.title ?? `sw-cms.detail.label.pageType.${camelCase}`,
            class: newTypeData.class ?? [],
            hideInList: !!newTypeData.hideInList,
        } as PageType;

        const cssClass = `sw-cms-create-wizard__page-type-${kebabCase}`;
        if (!newType.class.includes(cssClass)) {
            newType.class.push(cssClass);
        }

        this.#state.pageTypes.push(newType);
    }

    getTypes(): PageType[] {
        return this.#state.pageTypes;
    }

    getVisibleTypes(): PageType[] {
        return this.#state.pageTypes.filter(pageType => !pageType.hideInList);
    }

    getTypeNames(): string[] {
        return this.#state.pageTypes.map(pageType => pageType.name);
    }

    getType(type?: string): PageType | undefined {
        return this.#state.pageTypes.find(pageType => pageType.name === type);
    }
}
