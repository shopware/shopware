/**
 * Postman Pre-Request Script for ContentSystem Media Upload
 *
 * Authenticates to Shopware Admin API and stores access token.
 * Use in Collection pre-request scripts or individual requests.
 */

const shopwareUrl = pm.environment.get("shopwareUrl") || "http://localhost:8000";
const username = pm.environment.get("adminUsername") || "admin";
const password = pm.environment.get("adminPassword") || "shopware";

// Authenticate if no token exists
if (!pm.environment.get("adminToken")) {
    pm.sendRequest({
        url: `${shopwareUrl}/api/oauth/token`,
        method: 'POST',
        header: { 'Content-Type': 'application/json' },
        body: {
            mode: 'raw',
            raw: JSON.stringify({
                client_id: "administration",
                grant_type: "password",
                scopes: "write",
                username: username,
                password: password
            })
        }
    }, (err, response) => {
        if (err) throw new Error("Authentication failed");

        const token = response.json().access_token;
        if (!token) throw new Error("No access token received");

        pm.environment.set("adminToken", token);
    });
}
