import type Repository from 'src/core/data/repository.data';
import type CriteriaType from 'src/core/data/criteria.data';

import template from './sw-experience-studio-list.html.twig';
import './sw-experience-studio-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

interface ColumnConfig {
    property: string;
    label: string;
    routerLink: string;
    allowResize: boolean;
    primary: boolean;
}

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data(): {
        layouts: EntityCollection<'content_layout'> | null;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
    } {
        return {
            layouts: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        layoutRepository(): Repository<'content_layout'> {
            return this.repositoryFactory.create('content_layout');
        },

        columnConfig(): ColumnConfig[] {
            return [
                {
                    property: 'name',
                    label: 'sw-experience-studio.list.columnName',
                    routerLink: 'sw.experience.studio.detail',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'version',
                    label: 'sw-experience-studio.list.columnVersion',
                    routerLink: 'sw.experience.studio.detail',
                    allowResize: true,
                    primary: false,
                },
                {
                    property: 'createdAt',
                    label: 'sw-experience-studio.list.columnCreatedAt',
                    routerLink: 'sw.experience.studio.detail',
                    allowResize: true,
                    primary: false,
                },
                {
                    property: 'updatedAt',
                    label: 'sw-experience-studio.list.columnUpdatedAt',
                    routerLink: 'sw.experience.studio.detail',
                    allowResize: true,
                    primary: false,
                },
            ];
        },

        criteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            if (this.term) {
                criteria.setTerm(this.term);
            }

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return criteria;
        },

        allowCreate(): boolean {
            return this.acl.can('experience_studio.creator');
        },
    },

    created(): void {
        void this.getList();
    },

    methods: {
        async getList(): Promise<void> {
            this.isLoading = true;

            this.layouts = await this.layoutRepository.search(this.criteria, Shopware.Context.api);
            this.total = this.layouts.total ?? 0;

            this.isLoading = false;
        },

        onCreateNewLayout(): void {
            void this.$router.push({ name: 'sw.experience.studio.create' });
        },

        onChangeLanguage(): void {
            void this.getList();
        },
    },
});
