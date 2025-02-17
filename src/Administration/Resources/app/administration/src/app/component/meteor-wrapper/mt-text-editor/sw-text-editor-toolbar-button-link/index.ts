import type { PropType } from 'vue';
import type { Editor } from '@tiptap/vue-3';
// eslint-disable-next-line max-len
import type { CustomButton } from '@shopware-ag/meteor-component-library/dist/esm/components/form/mt-text-editor/_internal/mt-text-editor-toolbar';
import template from './sw-text-editor-toolbar-button-link.html.twig';
import './sw-text-editor-toolbar-button-link.scss';
import type EntityCollectionType from '../../../../../core/data/entity-collection.data';
import type RepositoryType from '../../../../../core/data/repository.data';
import type CriteriaType from '../../../../../core/data/criteria.data';

type LinkCategories = 'link' | 'detail' | 'navigation' | 'media' | 'email' | 'phone';

const { Criteria, EntityCollection } = Shopware.Data;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Custom link button for the Meteor text editor.
 */
Shopware.Component.register('sw-text-editor-toolbar-button-link', {
    template,

    inject: [
        'repositoryFactory',
    ],

    props: {
        editor: {
            type: Object as PropType<Editor>,
            required: true,
        },
        button: {
            type: Object as PropType<CustomButton>,
            required: true,
        },
        disabled: {
            type: Boolean,
            default: false,
        },
    },

    data(): {
        isLoading: boolean;
        showLinkModal: boolean;
        linkHref: string;
        linkTarget: string | null;
        linkType: LinkCategories;
        categoryCollection: EntityCollectionType<'category'> | null;
        displayAsButton: boolean;
        buttonVariant: string;
    } {
        return {
            isLoading: true,
            showLinkModal: false,
            linkHref: '',
            linkTarget: null,
            linkType: 'link',
            categoryCollection: null,
            displayAsButton: false,
            buttonVariant: 'primary',
        };
    },

    computed: {
        linkOptions() {
            return [
                {
                    id: 'link',
                    label: this.$t('sw-text-editor-toolbar-button-link.linkOptions.link'),
                    value: 'link',
                },
                {
                    id: 'detail',
                    label: this.$t('sw-text-editor-toolbar-button-link.linkOptions.product'),
                    value: 'detail',
                },
                {
                    id: 'navigation',
                    label: this.$t('sw-text-editor-toolbar-button-link.linkOptions.category'),
                    value: 'navigation',
                },
                {
                    id: 'media',
                    label: this.$t('sw-text-editor-toolbar-button-link.linkOptions.media'),
                    value: 'media',
                },
                {
                    id: 'email',
                    label: this.$t('sw-text-editor-toolbar-button-link.linkOptions.email'),
                    value: 'email',
                },
                {
                    id: 'phone',
                    label: this.$t('sw-text-editor-toolbar-button-link.linkOptions.phoneNumber'),
                    value: 'phone',
                },
            ];
        },

        buttonVariantList() {
            return [
                {
                    id: 'primary',
                    value: 'primary',
                    label: this.$tc('sw-text-editor-toolbar-button-link.buttonVariantPrimary'),
                },
                {
                    id: 'secondary',
                    value: 'secondary',
                    label: this.$tc('sw-text-editor-toolbar-button-link.buttonVariantSecondary'),
                },
                {
                    id: 'primary-sm',
                    value: 'primary-sm',
                    label: this.$tc('sw-text-editor-toolbar-button-link.buttonVariantPrimarySmall'),
                },
                {
                    id: 'secondary-sm',
                    value: 'secondary-sm',
                    label: this.$tc('sw-text-editor-toolbar-button-link.buttonVariantSecondarySmall'),
                },
            ];
        },

        seoUrlReplacePrefix(): string {
            // Hardcoded SEO URL prefix ID
            return '124c71d524604ccbad6042edce3ac799';
        },

        categoryRepository(): RepositoryType<'category'> {
            return this.repositoryFactory.create('category');
        },

        showOpenInNewTabToggle() {
            return [
                'link',
                'detail',
                'navigation',
                'media',
            ].includes(this.linkType);
        },

        productEntityFilter(): CriteriaType {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('options.group');

            criteria.addFilter(
                Criteria.multi('OR', [
                    Criteria.equals('product.childCount', 0),
                    Criteria.equals('product.childCount', null),
                ]),
            );

            return criteria;
        },

        entityFilter(): CriteriaType {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('options.group');

            criteria.addFilter(
                Criteria.multi('OR', [
                    Criteria.equals('product.childCount', 0),
                    Criteria.equals('product.childCount', null),
                ]),
            );

            return criteria;
        },
    },

    created() {
        this.categoryCollection = this.getEmptyCategoryCollection();
    },

    methods: {
        async openLinkModal() {
            this.isLoading = true;
            this.showLinkModal = true;

            // Get current link from selection
            this.linkHref = (this.editor.getAttributes('link').href as string) ?? '';
            this.linkTarget = (this.editor.getAttributes('link').target as string) ?? '';

            // Parse link type
            const { linkType, linkHref } = await this.parseLink(this.linkHref);

            // Parse link class
            this.displayAsButton = (this.editor.getAttributes('link').class as string)?.includes('btn');

            if (this.displayAsButton) {
                this.buttonVariant = this.parseButtonClass();
            }

            this.linkType = linkType;
            this.linkHref = linkHref;

            // Finish loading
            this.isLoading = false;
        },

        async parseLink(link: string): Promise<{
            linkType: LinkCategories;
            linkHref: string;
        }> {
            const slicedLink = link.slice(0, -1).split('/');

            if (
                link.startsWith(this.seoUrlReplacePrefix) &&
                [
                    'navigation',
                    'detail',
                    'mediaId',
                ].includes(slicedLink[1])
            ) {
                if (slicedLink[1] === 'navigation') {
                    this.categoryCollection = await this.getCategoryCollection(slicedLink[2]);
                } else if (slicedLink[1] === 'mediaId') {
                    slicedLink[1] = 'media';
                }

                return {
                    linkType: slicedLink[1] as LinkCategories,
                    linkHref: slicedLink[2],
                };
            }

            if (link.startsWith('mailto:')) {
                return {
                    linkType: 'email',
                    linkHref: link.replace('mailto:', ''),
                };
            }

            if (link.startsWith('tel:')) {
                return {
                    linkType: 'phone',
                    linkHref: link.replace('tel:', ''),
                };
            }

            // When nothing was found use "link" as default
            return {
                linkType: 'link',
                linkHref: link,
            };
        },

        parseButtonClass(): string {
            // Get the correct button type from the class
            const fullButtonClass = (this.editor.getAttributes('link').class as string) ?? '';
            const buttonClasses = fullButtonClass.split(' ');

            const buttonVariant = this.buttonVariantList.find((variant) => {
                // Check if one of the button classes matches exactly the variant value
                // "includes" does not work here because it would match "primary" in "primary-sm"
                return buttonClasses.find((buttonClass: string) => {
                    if (buttonClass === `btn-${variant.value}`) {
                        return variant;
                    }

                    return false;
                });
            });

            return buttonVariant?.value ?? 'primary';
        },

        applyLink() {
            const href = this.prepareLink();
            const linkClass = this.prepareClass();

            this.prepareTarget();

            this.editor
                .chain()
                .focus()
                .extendMarkRange('link')
                .setLink({
                    href,
                    target: this.linkTarget,
                    class: linkClass,
                })
                .run();

            this.showLinkModal = false;
        },

        removeLink() {
            this.editor.chain().focus().unsetLink().run();

            this.showLinkModal = false;
        },

        isLink() {
            return this.editor.isActive('link');
        },

        prepareLink() {
            switch (this.linkType) {
                case 'detail':
                    return `${this.seoUrlReplacePrefix}/detail/${this.linkHref}#`;
                case 'navigation':
                    return `${this.seoUrlReplacePrefix}/navigation/${this.linkHref}#`;
                case 'media':
                    return `${this.seoUrlReplacePrefix}/mediaId/${this.linkHref}#`;
                case 'email':
                    return `mailto:${this.linkHref}`;
                case 'phone':
                    return `tel:${this.linkHref.replace(/\//, '')}`;
                default:
                    return this.addProtocolToLink(this.linkHref);
            }
        },

        prepareClass() {
            return this.displayAsButton ? `btn btn-${this.buttonVariant}` : undefined;
        },

        prepareTarget() {
            // Remove link target "_blank" if it is not allowed
            if (!this.showOpenInNewTabToggle) {
                this.linkTarget = null;
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
                link = `https://${link}`;
            }

            return link;
        },

        getCategoryCollection(categoryId: string): Promise<EntityCollectionType<'category'>> {
            const categoryCriteria = new Criteria(1, 25).addFilter(Criteria.equals('id', categoryId));
            return this.categoryRepository.search(categoryCriteria);
        },

        getEmptyCategoryCollection(): EntityCollectionType<'category'> {
            return new EntityCollection(
                this.categoryRepository.route,
                this.categoryRepository.entityName,
                Shopware.Context.api,
            );
        },

        replaceCategorySelection(category: { id: string }): void {
            this.linkHref = category.id;
        },

        removeCategorySelection(): void {
            this.linkHref = '';
        },

        onSelectFieldChange(linkType: LinkCategories) {
            this.linkType = linkType;
            this.linkHref = '';
        },
    },
});
