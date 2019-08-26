import VuexModules from 'src/app/state/index';

export default function createAppStates() {
    const factoryContainer = this.getContainer('factory');
    const serviceContainer = this.getContainer('service');
    const stateFactory = factoryContainer.state;
    const UploadStore = Shopware.DataDeprecated.UploadStore;

    stateFactory.registerStore('upload', new UploadStore(
        serviceContainer.mediaService
    ));

    return Object.keys(VuexModules).map((storeModule) => {
        return stateFactory.registerStore(storeModule, VuexModules[storeModule]);
    });
}
