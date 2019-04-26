[titleEn]: <>(Naming database constraints)

With the newest MySQL version `CONSTRAINTS` must be unique across all tables. This means that

`CONSTRAINT json.attributes CHECK (JSON_VALID(attributes))` is no longer valid. The new constraint name should be:

`CONSTRAINT json.table_name.attributes CHECK (JSON_VALID(attributes))`. This is true for all CONSTRAINT, not only JSON_VALID().
