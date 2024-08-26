/* eslint no-console: 0 */
const createLiveReloadServer = require('./live-reload-server/index');
const createProxyServer = require('./proxy-server-hot/index');

// starting the live reload server
const server = createLiveReloadServer();

server.then(() => {
    const fullUrl = process.env.APP_URL;
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
    };

    // starting the proxy server
    createProxyServer(proxyServerOptions).then(({ proxyUrl } ) => {
        console.log('############');
        console.log(`Storefront proxy server started at ${proxyUrl}`);
        console.log('############');
        console.log('\n');
    });
});
