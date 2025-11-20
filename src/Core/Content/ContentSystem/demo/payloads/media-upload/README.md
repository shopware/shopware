# Media Upload from Remote URLs

Upload hero banner images (AI generated for this demo data) from imgbb.com to Shopware using Admin API.

## Files

- `upload-media-postman-pre-script.js` - Authentication pre-request script for Postman
- `01-upload-about-hero.json` - About hero banner payload
- `02-upload-smart-hub-hero.json` - Smart Hub hero banner payload
- `03-upload-summer-sale-hero.json` - Summer Sale hero banner payload
- `04-upload-homepage-hero.json` - Homepage hero image payload

## Usage in Postman

1. Create request: `POST {{shopwareUrl}}/api/_action/media/upload_by_url`
2. Authorization: Bearer Token → `{{adminToken}}`
3. Pre-request Script: Copy content from `upload-media-postman-pre-script.js`
4. Body → raw → JSON: Copy content from payload files (e.g., `01-upload-about-hero.json`)
5. Send

Repeat for all 4 payload files.

## Images to Upload

| Filename | Remote URL | Media ID |
|----------|------------|----------|
| `about-hero.png` | `https://i.ibb.co/zTyYZDzK/about-us.png` | `85748bf50c354b1293baa1df939d02ba` |
| `smart-hub-hero.png` | `https://i.ibb.co/x8Rrw19X/automation-hub.png` | `e73eedeb51554c7e940b90ea69f42b1b` |
| `summer-sale-hero.png` | `https://i.ibb.co/1fy15y2p/summer-sale.png` | `d2cb2dc264214578a16350c301e40535` |
| `homepage-hero.png` | `https://i.ibb.co/B5Rb9pzX/homepage.png` | `5c9baa54f14048c9af0cdd8eb3da7846` |

Upload media BEFORE running Sync API demo payload.
