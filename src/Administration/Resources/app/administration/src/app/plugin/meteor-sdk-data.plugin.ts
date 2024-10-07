import type { App } from 'vue';

/**
 * @package admin
 */
const MeteorSdkDataPlugin = {
    install(app: App) {
        app.mixin({
            data() {
                return {
                    dataSetUnwatchers: [],
                };
            },

            beforeUnmount() {
                // @ts-expect-error
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-return
                this.dataSetUnwatchers.forEach((unwatch) => unwatch());
            },
        });
    },
};

/* @private */
export default MeteorSdkDataPlugin;
