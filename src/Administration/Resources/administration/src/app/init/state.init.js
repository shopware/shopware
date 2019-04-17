import ErrorStore from 'src/core/data/ErrorStore';
import UploadStore from 'src/core/data/UploadStore';
import VuexModules from 'src/app/state/index';

export default function createCoreStates() {
    const factoryContainer = this.getContainer('factory');
    const serviceContainer = this.getContainer('service');
    const stateFactory = factoryContainer.state;

    stateFactory.registerStore('error', new ErrorStore());
    stateFactory.registerStore('upload', new UploadStore(
        serviceContainer.mediaService
    ));

    Object.keys(VuexModules).forEach((storeModule) => {
        stateFactory.registerStore(storeModule, VuexModules[storeModule]);
    });

    return true;
}
