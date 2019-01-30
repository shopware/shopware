import '@babel/polyfill';
import './sass/app.scss';

export default class Application {
    constructor() {
        this.name = 'Application';
    }
}

console.log(new Application());

// Necessary for the webpack hot module reloading server
if (module.hot) {
    module.hot.accept();
}