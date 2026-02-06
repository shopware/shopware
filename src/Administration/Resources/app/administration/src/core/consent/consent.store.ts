/**
 * @private
 * @sw-package framework:fundamentals
 */
export type ConsentDTO = {
    readonly name: string;
    readonly identifier: string;
    readonly scopeName: 'system' | 'admin_user';
    readonly actor: string | null;
    readonly status: 'accepted' | 'revoked' | 'unset';
    readonly updated_at: string | null;
};

type ConsentStoreState = {
    consents: Record<string, ConsentDTO>;
};

/**
 * @private
 */
export default Shopware.Store.register('consent', {
    state: (): ConsentStoreState => ({
        consents: {},
    }),

    actions: {
        async update(): Promise<void> {
            const { data } = await Shopware.Service('consentApiService').list();

            this.consents = data;
        },

        async accept(name: string): Promise<void> {
            if (!this.consents[name]) {
                throw new Error(`Consent with name "${name}" not found in store.`);
            }

            if (this.consents[name].status === 'accepted') {
                return;
            }

            const { data: updatedConsent } = await Shopware.Service('consentApiService').accept(name);

            this.consents[name] = updatedConsent;
        },

        async revoke(name: string): Promise<void> {
            if (!this.consents[name]) {
                throw new Error(`Consent with name "${name}" not found in store.`);
            }

            if (this.consents[name].status === 'revoked') {
                return;
            }

            const { data: updatedConsent } = await Shopware.Service('consentApiService').revoke(name);

            this.consents[name] = updatedConsent;
        },

        isAccepted(name: string): boolean {
            if (!this.consents[name]) {
                throw new Error(`Consent with name "${name}" not found in store.`);
            }

            return this.consents[name].status === 'accepted';
        },
    },
});
