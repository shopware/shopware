/**
 * @private
 * @sw-package framework:fundamentals
 */
export type ConsentDTO = {
    readonly name: string,
    readonly identifier: string,
    status: 'accepted' | 'revoked' | 'requested',
}

type ConsentStoreState = {
    consents: Record<string, ConsentDTO>;
}

/**
 * @private
 */
export default Shopware.Store.register('consent',{
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

            await Shopware.Service('consentApiService').accept(name);

            this.consents[name].status = 'accepted';
        },

        async revoke(name: string): Promise<void> {
            if (!this.consents[name]) {
                throw new Error(`Consent with name "${name}" not found in store.`);
            }

            if (this.consents[name].status === 'revoked') {
                return;
            }

            await Shopware.Service('consentApiService').revoke(name);

            this.consents[name].status = 'revoked';
        },

        isAccepted(name: string): boolean {
            if (!this.consents[name]) {
                throw new Error(`Consent with name "${name}" not found in store.`);
            }

            return this.consents[name].status === 'accepted';
        },
    },
});