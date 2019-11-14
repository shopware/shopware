export default function initState() {
    const factoryContainer = this.getContainer('factory');
    const stateFactoryDeprecated = factoryContainer.stateDeprecated;
    const UploadStore = Shopware.DataDeprecated.UploadStore;

    return stateFactoryDeprecated.registerStore('upload', new UploadStore(
        Shopware.Service('mediaService')
    ));
}
