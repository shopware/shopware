export default class ExtensionHelperService {
    constructor({ storeService, pluginService, extensionApiService }) {
        this.storeService = storeService;
        this.pluginService = pluginService;
        this.extensionApiService = extensionApiService;
    }

    async downloadAndActivateExtension(extensionName) {
        const extensionStatus = await this.getStatusOfExtension(extensionName);

        if (!extensionStatus.downloaded) {
            await this.downloadStoreExtension(extensionName);
        }

        if (!extensionStatus.installedAt) {
            await this.installStoreExtension(extensionName);
        }

        if (!extensionStatus.active) {
            await this.activateStoreExtension(extensionName);
        }
    }

    downloadStoreExtension(extensionName) {
        return this.storeService.downloadPlugin(extensionName, true, true);
    }

    installStoreExtension(extensionName) {
        return this.pluginService.install(extensionName);
    }

    activateStoreExtension(extensionName) {
        return this.pluginService.activate(extensionName);
    }

    async getStatusOfExtension(extensionName) {
        const extensions = await this.extensionApiService.getMyExtensions();
        const extension = extensions.find(e => e && e.name === extensionName);

        if (!extension) {
            return {
                downloaded: false,
                installedAt: false,
                active: false
            };
        }

        return {
            downloaded: true,
            installedAt: extension.installedAt,
            active: extension.active
        };
    }
}
