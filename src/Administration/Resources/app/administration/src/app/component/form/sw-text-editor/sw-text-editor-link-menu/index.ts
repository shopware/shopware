import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import type EntityCollectionType from 'src/core/data/entity-collection.data';
import template from './sw-text-editor-link-menu.html.twig';

const { Component } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

type LinkCategories = 'link' | 'detail' | 'category' | 'email' | 'phone';
interface TextEditorLinkMenuConfig {
    title: string,
    icon: string,
    expanded: boolean,
    newTab: boolean,
    displayAsButton: boolean,
    value: string,
    type: string,
    tag: 'a',
    active: false,
}

/**
 * @private
 */
Component.register('sw-text-editor-link-menu', {
    template,

    inject: [
        'repositoryFactory',
    ],

    props: {
        buttonConfig: {
            type: Object as () => TextEditorLinkMenuConfig,
            required: true,
        },
    },

    data(): {
        linkTitle: string,
        linkTarget: string,
        isHTTPs: boolean,
        opensNewTab: boolean,
        displayAsButton: boolean,
        linkCategory: LinkCategories,
        categoryCollection?: EntityCollectionType,
        } {
        return {
            linkTitle: '',
            linkTarget: '',
            isHTTPs: false,
            opensNewTab: false,
            displayAsButton: false,
            linkCategory: 'link',
            categoryCollection: undefined,
        };
    },

    computed: {
        seoUrlReplacePrefix(): string {
            return '124c71d524604ccbad6042edce3ac799';
        },

        entityFilter(): CriteriaType {
            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        categoryRepository(): RepositoryType {
            return this.repositoryFactory.create('category');
        },
    },

    watch: {
        buttonConfig: {
            async handler(buttonConfig): Promise<void> {
                const { title, newTab, displayAsButton, value, type } = buttonConfig as TextEditorLinkMenuConfig;
                this.linkTitle = title;
                this.opensNewTab = newTab;
                this.displayAsButton = displayAsButton;

                const parsedResult = await this.parseLink(value, type);
                this.linkCategory = parsedResult.type;
                this.linkTarget = parsedResult.target;
            },
            immediate: true,
        },
    },

    created(): void {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            this.categoryCollection = this.getEmptyCategoryCollection();
        },

        getCategoryCollection(categoryId: string): Promise<EntityCollectionType> {
            const categoryCriteria = (new Criteria()).addFilter(Criteria.equals('id', categoryId));
            return this.categoryRepository.search(categoryCriteria);
        },

        getEmptyCategoryCollection(): EntityCollectionType {
            return new EntityCollection(
                this.categoryRepository.route,
                this.categoryRepository.entityName,
                Shopware.Context.api,
            );
        },

        async parseLink(link: string, detectedLinkType: string): Promise<{ type: LinkCategories, target: string }> {
            const slicedLink = link.slice(0, -1).split('/');

            if (link.startsWith(this.seoUrlReplacePrefix)) {
                const [productId] = slicedLink.splice(-1);
                return { type: 'detail', target: productId };
            }

            if (link.startsWith('category')) {
                this.categoryCollection = await this.getCategoryCollection(slicedLink[2]);
                return {
                    type: 'category',
                    target: slicedLink[2],
                };
            }

            if (link.startsWith('mailto:')) {
                return {
                    type: 'email',
                    target: link.replace('mailto:', ''),
                };
            }

            if (link.startsWith('tel:')) {
                return {
                    type: 'phone',
                    target: link.replace('tel:', ''),
                };
            }

            return {
                target: link,
                type: detectedLinkType as LinkCategories,
            };
        },

        replaceCategorySelection(category: {id: string}): void {
            this.linkTarget = category.id;
        },

        removeCategorySelection(): void {
            this.linkTarget = '';
        },

        prepareLink(): string {
            switch (this.linkCategory) {
                case 'detail':
                    return `${this.seoUrlReplacePrefix}/detail/${this.linkTarget}#`;
                case 'category':
                    return `${this.seoUrlReplacePrefix}/navigation/${this.linkTarget}#`;
                case 'email':
                    return `mailto:${this.linkTarget}`;
                case 'phone':
                    return `tel:${this.linkTarget.replace(/\//, '')}`;
                default:
                    return this.addProtocolToLink(this.linkTarget);
            }
        },

        addProtocolToLink(link: string): string {
            if (/(^(\w+):\/\/)|(mailto:)|(fax:)|(tel:)/.test(link)) {
                return link;
            }

            const isInternal = /^\/[^\/\s]/.test(link);
            const isAnchor = link.substring(0, 1) === '#';
            const isProtocolRelative = /^\/\/[^\/\s]/.test(link);

            if (!isInternal && !isAnchor && !isProtocolRelative) {
                link = `http://${link}`;
            }

            return link;
        },

        setLink(): void {
            this.$emit('button-click', {
                type: 'link',
                value: this.prepareLink(),
                displayAsButton: this.displayAsButton ? 'primary' : false,
                newTab: this.opensNewTab,
            });
        },

        removeLink(): void {
            this.$emit('button-click', {
                type: 'linkRemove',
            });
        },

        onSelectFieldChange(): void {
            this.linkTarget = '';
        },
    },
});
