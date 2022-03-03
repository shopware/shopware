import template from './sw-text-editor-link-menu.html.twig';
import './sw-text-editor-link-menu.scss';

const { Component, Data: { Criteria, EntityCollection, Repository } } = Shopware;

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

    data() {
        return {
            linkTitle: '',
            linkTarget: '',
            isHTTPs: false,
            magicLinkPrefix: '124c71d524604ccbad6042edce3ac799',
            opensNewTab: false,
            displayAsButton: false,
            linkCategory: 'link' as LinkCategories,
            categoryCollection: undefined,
        };
    },
    created() {
        this.categoryCollection = this.getEmptyCategoryCollection();
    },

    methods: {
        getCategoryCollection(categoryId: string): Promise<EntityCollection> {
            const categoryCriteria = new Criteria().addFilter(Criteria.equals('id', categoryId));
            return this.categoryRepository.search(categoryCriteria) as Promise<EntityCollection>;
        },

        getEmptyCategoryCollection(): EntityCollection {
            return new EntityCollection(
                this.categoryRepository.route,
                this.categoryRepository.entityName,
                Shopware.Context,
            );
        },

        async parseLink(link: string, detectedLinkType: string): Promise<{ type: LinkCategories, target: string }> {
            const slicedLink = link.slice(0, -1).split('/');

            if (link.startsWith(this.magicLinkPrefix)) {
                const [productId] = slicedLink.splice(-1);
                return { type: 'detail', target: productId };
            }

            if (link.startsWith('category')) {
                this.categoryCollection = await this.getCategoryCollection(slicedLink[2]);
                return { type: 'category', target: slicedLink[2] };
            }

            if (link.startsWith('mailto:')) {
                return { type: 'email', target: link.replace('mailto:', '') };
            }

            if (link.startsWith('tel:')) {
                return { type: 'phone', target: link.replace('tel:', '') };
            }

            return { target: link, type: detectedLinkType as LinkCategories };
        },

        replaceCategorySelection(category: {id: string}) {
            this.linkTarget = category.id;
        },

        removeCategorySelection() {
            this.linkTarget = '';
        },

        prepareLink() {
            if (this.linkCategory === 'detail') {
                return `${this.magicLinkPrefix}/detail/${this.linkTarget}#`;
            }

            if (this.linkCategory === 'category') {
                return `category/${this.magicLinkPrefix}/${this.linkTarget}#`;
            }

            if (this.linkCategory === 'email') {
                return `mailto:${this.linkTarget}`;
            }

            if (this.linkCategory === 'phone') {
                return `tel:${this.linkTarget}`;
            }

            return this.addProtocolToLink(this.linkTarget);
        },

        addProtocolToLink(link:string) {
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

        getCategoryLabel(entity: {name: string, breadcrumb: string[]}) {
            const breadcrumbPath = entity.breadcrumb?.join('/');

            if (!breadcrumbPath) {
                return entity.name;
            }

            return `${breadcrumbPath}/${entity.name}`;
        },

        setLink() {
            this.$emit('button-click', {
                type: 'link',
                value: this.prepareLink(),
                displayAsButton: this.displayAsButton ? 'primary' : false,
                newTab: this.opensNewTab,
            });
        },
        removeLink() {
            this.$emit('button-click', {
                type: 'linkRemove',
            });
        },
    },

    watch: {
        buttonConfig: {
            async handler(buttonConfig) {
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
        linkCategory: {
            handler() {
                this.linkTarget = '';
                this.linkTitle = '';
                this.opensNewTab = false;
                this.displayAsButton = false;
            },
        },

    },

    computed: {
        entityFilter() {
            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        categoryRepository(): Repository {
            return this.repositoryFactory.create('category');
        },
    },
});
