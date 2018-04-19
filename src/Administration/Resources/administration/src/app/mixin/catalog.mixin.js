import { Mixin, Entity } from 'src/core/shopware';
import utils from '../../core/service/util.service';

/**
 * @module app/mixin/catalog
 */
Mixin.register('catalog', {
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.catalog.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    data() {
        return {
            catalogId: null,
            isLoading: false,
            isLoaded: false,
            catalog: Entity.getRawEntityObject('catalog', true)
        };
    },

    computed: {
        catalogState() {
            return this.$store.state.catalog.draft[this.catalogId];
        },

        requiredCatalogFields() {
            return Entity.getRequiredProperties('catalog');
        }
    },

    watch: {
        catalog: {
            deep: true,
            handler() {
                this.commitCatalog();
            }
        }
    },

    mounted() {
        if (this.$route.name.includes('sw.catalog.create')) {
            this.createEmptyCatalog(this.catalogId);
        } else {
            this.getCatalogById(this.catalogId);
        }
    },

    methods: {
        getCatalogById(catalogId) {
            this.isLoading = true;

            return this.$store.dispatch('catalog/getById', catalogId).then(() => {
                this.isLoaded = true;
                this.isLoading = false;
                this.catalog = this.catalogState;

                return this.catalog;
            });
        },

        createEmptyCatalog(catalogId) {
            return this.$store.dispatch('catalog/createEmpty', catalogId).then(() => {
                this.isLoaded = true;
                this.catalog = this.catalogState;
            });
        },

        saveCatalog() {
            this.isLoading = true;

            return this.$store.dispatch('catalog/saveItem', this.catalog).then((catalog) => {
                this.isLoading = false;

                if (this.$route.name.includes('sw.catalog.create')) {
                    this.$router.push({ name: 'sw.catalog.detail', params: { id: catalog.id } });
                }
            }).catch(() => {
                this.isLoading = false;
            });
        },

        commitCatalog: utils.throttle(function throttledCommitCatalog() {
            return this.$store.commit('catalog/setItem', this.catalog);
        }, 500)
    }
});
