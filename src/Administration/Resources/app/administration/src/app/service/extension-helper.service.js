export default class ExtensionHelperService {
    constructor({ storeService, extensionStoreActionService }) {
        this.storeService = storeService;
        this.extensionStoreActionService = extensionStoreActionService;
    }

    async downloadAndActivateExtension(extensionName, type = 'plugin') {
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

    downloadStoreExtension(extensionName) {
        return this.extensionStoreActionService.downloadExtension(extensionName);
    }

    installStoreExtension(extensionName, type) {
        return this.extensionStoreActionService.installExtension(extensionName, type);
    }

    activateStoreExtension(extensionName, type) {
        return this.extensionStoreActionService.activateExtension(extensionName, type);
    }

    async getStatusOfExtension(extensionName) {
        const extensions = await this.extensionStoreActionService.getMyExtensions();
        const extension = extensions.find(e => e && e.name === extensionName);

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
