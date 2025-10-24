# ContentSystem Demo

Demo data and Postman scripts for testing the ContentSystem.

## Files

- `sync-api-demo-payload.json` - Demo data with products, categories, landing pages, layouts, and routes
- `sync-api-demo-post-pre-script.js` - Postman pre-request script for authentication and ID resolution

## Usage in Postman

### 1. Setup Pre-Request Script

1. Create new Postman request: `POST http://localhost:8000/api/_action/sync`
2. Copy content from `sync-api-demo-post-pre-script.js` to request's "Pre-request Script" tab
3. Set authorization: "Bearer Token" with `{{adminToken}}`

### 2. Import Demo Payload

1. Go to request "Body" tab
2. Select "raw" and "JSON"
3. Copy content from `sync-api-demo-payload.json`

### 3. Execute

Run the request. The pre-request script will:
- Authenticate as admin
- Fetch required IDs (sales channel, tax, payment method, etc.)
- Replace placeholders in payload automatically

## Placeholder Variables

The payload uses these placeholders (auto-populated by pre-request script):

- `{{salesChannelId}}` - Storefront sales channel
- `{{taxId}}` - Tax with 19% rate
- `{{paymentMethodId}}` - First active payment method
- `{{shippingMethodId}}` - First active shipping method
- `{{countryId}}` - First active country
- `{{snippetSetId}}` - English (GB) snippet set
