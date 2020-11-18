/**
 * This module creates an proxy server. It is used in Shopware storefront for the
 * hot module replacement to allow the server to automatically detect if the hot mode
 * is activated or not.
 */

const { createServer, request } = require('http');
const { spawn } = require('child_process');

module.exports = function createProxyServer({ appPort, originalHost, proxyHost, proxyPort }) {
    const proxyUrl = `${proxyHost}:${proxyPort}`;

    const originalUrl = appPort !== 80 && appPort !== 443 ? `${originalHost}:${appPort}` : originalHost;

    // Create the HTTP proxy
    const server = createServer((client_req, client_res) => {
        const requestOptions = {
            port: appPort,
            path: client_req.url,
            method: client_req.method,
            headers: {
                ...client_req.headers,
                host: originalUrl,
                'hot-reload-mode': true,
                'hot-reload-port': process.env.STOREFRONT_ASSETS_PORT || 9999,
                'accept-encoding': 'identity'
            }
        };

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

    }).listen(proxyPort);

    // open the browser with the proxy url
    openBrowserWithUrl(proxyUrl);

    return Promise.resolve({ server, proxyUrl });
};

function openBrowserWithUrl(url) {
    const childProcessOptions = {
        stdio: 'ignore',
        detached: true
    };

    try {
        const start = (process.platform === 'darwin' ? 'open' : process.platform === 'win32' ? 'start' : 'xdg-open');
        const child = spawn(start, [url], childProcessOptions);

        child.on('error', error => {
            console.log('Unable to open browser! Details:');
            console.log(error);
        })
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
        const responseBody = responseData.replace(new RegExp(`${originalUrl}/`, 'g'), `${proxyUrl}/`);

        // end the client response with sufficient headers
        clientResponse.writeHead(response.statusCode, response.headers);
        clientResponse.end(responseBody);
    });
}
