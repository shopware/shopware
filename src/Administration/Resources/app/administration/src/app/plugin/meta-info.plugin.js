/**
 * @package admin
 */

import { getCurrentInstance, watchEffect } from 'vue';

const { warn } = Shopware.Utils.debug;

class MetaInfoPlugin {
    pluginInstalled = false;

    isMetaInfoPluginInstalled() {
        return this.pluginInstalled;
    }

    install(app) {
        if (this.pluginInstalled) {
            warn('Meta Info Plugin', 'This plugin is already installed');
            return false;
        }

        app.mixin({
            data() {
                return {
                    metaInfoWatchStopHandle: null,
                };
            },

            created() {
                const instance = getCurrentInstance();
                if (!instance?.type || !('metaInfo' in instance.type)) {
                    return;
                }

                const metaInfoOption = instance.type.metaInfo;
                if (typeof metaInfoOption === 'function') {
                    this.metaInfoWatchStopHandle = watchEffect(() => {
                        const metaInfo = metaInfoOption.call(this);
                        if (metaInfo && typeof metaInfo === 'object' && metaInfo.hasOwnProperty('title')) {
                            document.title = metaInfo.title;
                        }
                    });
                } else {
                    // eslint-disable-next-line max-len
                    warn(
                        'Meta Info Plugin',
                        'Providing the metaInfo as an object is not supported anymore. Please use a function instead.',
                    );
                }
            },

            beforeUnmount() {
                if (!this.metaInfoWatchStopHandle) {
                    return;
                }

                this.metaInfoWatchStopHandle();
            },
        });

        this.pluginInstalled = true;

        return true;
    }
}

/**
 * @private
 */
export default new MetaInfoPlugin();
