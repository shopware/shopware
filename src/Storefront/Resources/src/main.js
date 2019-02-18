// Provides polyfills based on the configured browser list
import '@babel/polyfill';
import 'bootstrap';

// Import styles
import './assets/sass/main.scss';

// import Client from './service/http-client.service';
// import Plugin from './helper/plugin.helper';

// get listing example
// const client = new Client(window.accessKey, window.contextToken);

// client.get('product?page=1&limit=10', function(response) {
//     console.log('client response', response);
// });

// plugin example
// const plugin = new Plugin('sw-simple-vanilla-plugin');
// plugin.on('initialized', () => {
//     console.log(
//         `Plugin %c"${plugin.name}" %cgot initialized`,
//         'font-weight: bold',
//         'font-weight: normal'
//     );
// });

// Necessary for the webpack hot module reloading server
if (module.hot) {
    module.hot.accept();
}
