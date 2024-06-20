const os = require('os');
const crypto = require('crypto');

const createLiveReloadServer = require('./live-reload-server/index');
const createProxyServer = require('./proxy-server-hot/index');
const path = require('path');

const socketName = crypto.randomBytes(4).readUInt32LE(0);
const socketPath = `${os.tmpdir()}/shopware-${socketName}.sock`;

// starting the live reload server
const server = createLiveReloadServer(socketPath);

const projectRootPath = process.env.PROJECT_ROOT
    ? path.resolve(process.env.PROJECT_ROOT)
    : path.resolve('../../../../..');

const themeFilesConfigPath = path.resolve(projectRootPath, 'var/theme-files.json');
const themeFiles = require(themeFilesConfigPath);

server.then(() => {
    const fullUrl = themeFiles.domainUrl;
    const proxyUrl = new URL(process.env.PROXY_URL || process.env.APP_URL);
    const proxyPort = process.env.STOREFRONT_PROXY_PORT;

    // first value of array is the http protocol
    const [schema, domainWithPortAndUri] = fullUrl.split('://');
    const [domainWithPort, ...uri] = domainWithPortAndUri.split('/');
    const [domain, port] = domainWithPort.split(':');

    const proxyServerOptions = {
        schema,
        originalHost: domain,
        appPort: port || 80,
        proxyHost: proxyUrl.hostname,
        proxyPort: parseInt(proxyPort || 9998),
        uri: uri.join('/'),
        socketPath,
    };

    // starting the proxy server
    createProxyServer(proxyServerOptions).then(({ proxyUrl } ) => {
        console.log('############');
        console.log(`Storefront proxy server started at ${proxyUrl}`);
        console.log(`Shopware storefront is available at ${fullUrl} (Use environment variable APP_URL to change)`);
        console.log(`Replacing all links to ${proxyUrl} (Use environment variable PROXY_URL to change)`);
        console.log('############');
        console.log('\n');
    });
});
