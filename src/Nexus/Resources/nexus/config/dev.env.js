const merge = require('webpack-merge');
const prodEnv = require('./prod.env');
const process = require('process');
const cliParameters = process.argv.slice(2);

let path = 'localhost';

if (cliParameters.length) {
    path = cliParameters[0];
}
console.log(`The API was configured to be accessible under "http://${path}/api". 
If you want to change the path, please provide the path (without the protocol) as a CLI parameter e.g.:
   $ npm run dev -- <your-path>
or
   $ yarn run dev -- <your-path>
   
If you're using PSH, you can run the following command to automatically set the path:
   $ ./psh.phar nexus:watch
`);

module.exports = merge(prodEnv, {
    NODE_ENV: '"development"',
    BASE_PATH: `"http://${path}"`
});
