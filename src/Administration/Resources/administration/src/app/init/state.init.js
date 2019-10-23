import VuexModules from 'src/app/state/index';

export default function createAppStates() {
    const factoryContainer = this.getContainer('factory');
    const stateFactoryDeprecated = factoryContainer.stateDeprecated;
    const UploadStore = Shopware.DataDeprecated.UploadStore;

    stateFactoryDeprecated.registerStore('upload', new UploadStore(
        Shopware.Service('mediaService')
    ));

    return Object.keys(VuexModules).map((storeModule) => {
        return Shopware.State.registerModule(storeModule, VuexModules[storeModule]);
    });
}
