/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';

const { Mixin } = Shopware;

/**
 * @private
 */
export default Mixin.register(
    'example-draft',
    defineComponent({
        computed: {
            draftStorage(): Storage {
                return window.sessionStorage;
            },
        },

        methods: {
            saveDraft(key: string, value: string): void {
                this.draftStorage.setItem(key, value);
            },

            restoreDraft(key: string): string | null {
                return this.draftStorage.getItem(key);
            },

            clearDraft(key: string): void {
                this.draftStorage.removeItem(key);
            },
        },
    }),
);
