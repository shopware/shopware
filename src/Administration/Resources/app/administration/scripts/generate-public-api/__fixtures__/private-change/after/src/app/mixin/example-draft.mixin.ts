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
            saveDraft(key: string, value: string, ttl: number): void {
                const expiresAt = Date.now() + ttl;

                this.draftStorage.setItem(key, JSON.stringify({ value, expiresAt }));
            },

            restoreDraft(key: string): string | null {
                const stored = this.draftStorage.getItem(key);

                if (stored === null) {
                    return null;
                }

                const { value, expiresAt } = JSON.parse(stored) as { value: string; expiresAt: number };

                if (expiresAt < Date.now()) {
                    this.draftStorage.removeItem(key);

                    return null;
                }

                return value;
            },
        },
    }),
);
