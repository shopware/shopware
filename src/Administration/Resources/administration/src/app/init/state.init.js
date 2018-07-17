import AuthStore from 'src/core/data/AuthStore';
import NotificationStore from 'src/core/data/NotificationStore';
import ErrorStore from 'src/core/data/ErrorStore';
import LocaleStore from 'src/core/data/LocaleStore';
import UploadStore from 'src/core/data/UploadStore';

export default function createCoreStates() {
    const factoryContainer = this.getContainer('factory');
    const stateFactory = factoryContainer.state;

    stateFactory.registerStore('auth', new AuthStore());
    stateFactory.registerStore('notification', new NotificationStore());
    stateFactory.registerStore('error', new ErrorStore());
    stateFactory.registerStore('adminLocale', new LocaleStore());
    stateFactory.registerStore('upload', new UploadStore());

    return true;
}
