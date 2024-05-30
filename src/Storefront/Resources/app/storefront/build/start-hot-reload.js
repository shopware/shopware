const createLiveReloadServer = require('./live-reload-server/index');
const createProxyServer = require('./proxy-server-hot/index');
const path = require("path");

// starting the live reload server
const server = createLiveReloadServer();

const projectRootPath = process.env.PROJECT_ROOT
    ? path.resolve(process.env.PROJECT_ROOT)
    : path.resolve('../../../../..');

const themeFilesConfigPath = path.resolve(projectRootPath, 'var/theme-files.json');
let themeFiles = require(themeFilesConfigPath);


server.then(() => {
    const proxyUrl = new URL(process.env.PROXY_URL || process.env.APP_URL);

    let port = 9998;
    Object.values(themeFiles).forEach((theme) => {
        const fullUrl = theme.domain;

        // first value of array is the http protocol
        const [schema, domainWithPortAndUri] = fullUrl.split('://');
        const [domainWithPort, ...uri] = domainWithPortAndUri.split('/');
        const [domain, domainPort] = domainWithPort.split(':');

        const proxyServerOptions = {
            schema,
            originalHost: domain,
            appPort: domainPort || 80,
            proxyHost: proxyUrl.hostname,
            proxyPort: parseInt(port = port + 2),
            uri: uri.join('/'),
        };

        // starting the proxy server
        createProxyServer(proxyServerOptions).then(({ proxyUrl } ) => {
            console.log('############');
            console.log(`${theme.themeName} proxy server started at ${proxyUrl}`);
            console.log('############');
            console.log('\n');
        });
    })
});
