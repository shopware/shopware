# Header and Footer Sections

Header and footer layouts use domain-aware resolution instead of entity-based rendering. They are independent of the main content and do not require a URL path.

**Header endpoints:**

| Endpoint                                   | Description         |
|--------------------------------------------|---------------------|
| `GET /store-api/content-header`            | Full response       |
| `GET /store-api/content-header-decomposed` | Decomposed response |
| `GET /store-api/content-header-skeleton`   | Skeleton only       |
| `GET /store-api/content-header-data`       | Data only           |

**Footer endpoints:**

| Endpoint                                   | Description         |
|--------------------------------------------|---------------------|
| `GET /store-api/content-footer`            | Full response       |
| `GET /store-api/content-footer-decomposed` | Decomposed response |
| `GET /store-api/content-footer-skeleton`   | Skeleton only       |
| `GET /store-api/content-footer-data`       | Data only           |

**Database tables:**
- `header_content_layout` - Header layout assignments
- `footer_content_layout` - Footer layout assignments

## Header/Footer Assignment Structure

An assignment record has these fields:
- `domainId` - Sales channel domain scope (`null` = not domain-specific); requires `salesChannelId`, enforced in the database by the CHECK constraint `chk.<table>.domain_requires_channel` on both tables above, not only by DAL validation
- `salesChannelId` - Sales channel scope (`null` = global)
- `contentLayoutId` - Layout to use

The pair (`domainId`, `salesChannelId`) is unique per table: index `uniq.<table>.domain_channel`.

## Domain-Aware Resolution

Resolution priority (three-tier fallback) via `DomainAwareLayoutResolver` (Core `Adapter/FactoryHelper`): **domain + sales channel** > **sales channel only** > **global** (both null).

Example: A shop with domains `shop.com` and `shop.de` can have different headers per domain, with a fallback header for the entire sales channel, and a global fallback for all channels.

## Header/Footer Placeholders

Header and footer layouts do not have entity-based placeholders. Query parameters passed to the endpoint become available as placeholders.

```
/store-api/content-header?activeCategoryId=abc123
```

Makes `{{activeCategoryId}}` available in the header layout.

Header and footer sections do not support partial rendering (`elementId` parameter).
