/* eslint no-console: 0 */

/**
 * This module creates a proxy server. It is used in Shopware storefront for the
 * hot module replacement to allow the server to automatically detect if the hot mode
 * is activated or not.
 */

const { createServer, request } = require('http');
const { spawn } = require('child_process');

module.exports = function createProxyServer({ schema, appPort, originalHost, proxyHost, proxyPort, uri }) {
    const proxyUrl = proxyPort !== 80 && proxyPort !== 443 ? `${proxyHost}:${proxyPort}`: proxyHost;
    const originalUrl = appPort !== 80 && appPort !== 443 ? `${originalHost}:${appPort}` : originalHost;

    let fullProxyUrl = `${schema}://${proxyUrl}/${uri || ''}`;
    if (fullProxyUrl.charAt(fullProxyUrl.length - 1) !== '/') {
        fullProxyUrl += '/';
    }

    // Create the HTTP proxy
    const server = createServer((client_req, client_res) => {
        try {
            //reject the connection when requesting from the wrong host
            const requestHost = client_req.hostname || client_req.headers.host;
            if (requestHost.split(':')[0] !== proxyHost) {
                //noinspection ExceptionCaughtLocallyJS
                throw 'Rejecting request "' + client_req.method + ' ' + proxyHost + client_req.url + '" on proxy server for "' + proxyUrl + '"';
            }

            const requestOptions = {
                host: originalHost,
                port: appPort,
                path: client_req.url,
                method: client_req.method,
                headers: {
                    ...client_req.headers,
                    host: originalUrl,
                    'hot-reload-mode': true,
                    'accept-encoding': 'identity',
                },
            };

            // Assets
            if (client_req.url.indexOf('/_webpack_hot_proxy_/') === 0) {
                requestOptions.host = '127.0.0.1';
                requestOptions.port = process.env.STOREFRONT_ASSETS_PORT || 9999;
                requestOptions.path = requestOptions.path.substr(20);
            }

            // Hot reload updates
            if (client_req.url.indexOf('/sockjs-node/') === 0 || client_req.url.indexOf('hot-update.json') !== -1 || client_req.url.indexOf('hot-update.js') !== -1) {
                requestOptions.host = '127.0.0.1';
                requestOptions.port = process.env.STOREFRONT_ASSETS_PORT || 9999;
            }

            // pipe a new request to the client request
            client_req.pipe(
                // request the data
                request(requestOptions, (response) => {
                    // replace urls from "redirects"
                    const contentType = String(response.headers['content-type']);

                    if (contentType.indexOf('text/html') >= 0 || contentType.indexOf('application/json') >= 0) {
                        replaceOriginalUrl(response, client_res, originalUrl, proxyUrl);
                        return;
                    }

                    client_res.writeHead(response.statusCode, response.headers);
                    response.pipe(client_res, {  end: true });
                }),
                {  end: true }
            );
        } catch (e) {
            console.error(e);
            client_req.destroy();
        }
    }).listen(proxyPort);

    // open the browser with the proxy url
    openBrowserWithUrl(fullProxyUrl);

    return Promise.resolve({ server, proxyUrl: fullProxyUrl });
};

function openBrowserWithUrl(url) {
    const childProcessOptions = {
        stdio: 'ignore',
        detached: true,
    };

    try {
        const start = (process.platform === 'darwin' ? 'open' : process.platform === 'win32' ? 'start' : 'xdg-open');
        const child = spawn(start, [url], childProcessOptions);

        child.on('error', error => {
            console.log('Unable to open browser! Details:');
            console.log(error);
        });
    } catch (ex) {
        console.log(ex);
    }
}

function replaceOriginalUrl(response, clientResponse, originalUrl, proxyUrl) {
    let responseData = '';

    // transform bitcode to readable utf8 text
    response.setEncoding('utf8');
    response.on('data', (chunk) => responseData += chunk);

    // when request is finished
    response.on('end', () => {
        // replace original url with proxy url
        const responseBody = responseData
            .replace(new RegExp(`${originalUrl}/`, 'g'), `${proxyUrl}/`)
            // Replace Symfony Profiler URL to relative url @see: https://regex101.com/r/HMQd2n/2
            .replace(/http[s]?\\u003A\\\/\\\/[\w.]*(:\d*|\\u003A\d*)?\\\/_wdt/gm, '/_wdt')
            .replace(/new\s*URL\(url\);\s*url\.searchParams\.set\('XDEBUG_IGNORE'/gm, 'new URL(window.location.protocol+\'//\'+window.location.host+url);                url.searchParams.set(\'XDEBUG_IGNORE\'');

        // end the client response with sufficient headers
        clientResponse.writeHead(response.statusCode, response.headers);
        clientResponse.end(responseBody);
    });
}
