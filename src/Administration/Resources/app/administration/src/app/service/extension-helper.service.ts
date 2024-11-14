// eslint-disable-next-line max-len
import type {
    ExtensionStoreActionService,
    ExtensionType,
} from '../../module/sw-extension/service/extension-store-action.service';

/**
 * @private
 * @package services-settings
 */
export default class ExtensionHelperService {
    private readonly extensionStoreActionService: ExtensionStoreActionService;

    constructor({ extensionStoreActionService }: { extensionStoreActionService: ExtensionStoreActionService }) {
        this.extensionStoreActionService = extensionStoreActionService;
    }

    async downloadAndActivateExtension(extensionName: string, type: ExtensionType = 'plugin') {
        const extensionStatus = await this.getStatusOfExtension(extensionName);

        if (!extensionStatus.downloaded) {
            await this.downloadStoreExtension(extensionName);
        }

        if (!extensionStatus.installedAt) {
            await this.installStoreExtension(extensionName, type);
        }

        if (!extensionStatus.active) {
            await this.activateStoreExtension(extensionName, type);
        }
    }

    downloadStoreExtension(extensionName: string) {
        return this.extensionStoreActionService.downloadExtension(extensionName);
    }

    installStoreExtension(extensionName: string, type: ExtensionType) {
        return this.extensionStoreActionService.installExtension(extensionName, type);
    }

    activateStoreExtension(extensionName: string, type: ExtensionType) {
        return this.extensionStoreActionService.activateExtension(extensionName, type);
    }

    async getStatusOfExtension(extensionName: string) {
        const extensions = await this.extensionStoreActionService.getMyExtensions();
        const extension = extensions.find((e) => e && e.name === extensionName);

        if (!extension) {
            return {
                downloaded: false,
                installedAt: false,
                active: false,
            };
        }

        return {
            downloaded: extension.source === 'local',
            installedAt: extension.installedAt,
            active: extension.active,
        };
    }
}

/**
 * @private
 */
export type { ExtensionHelperService };
