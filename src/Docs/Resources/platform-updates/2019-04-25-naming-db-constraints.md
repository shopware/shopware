[titleEn]: <>(Naming database constraints)

With the newest MySQL version `CONSTRAINTS` must be unique across all tables. This means that

`CONSTRAINT json.custom_fields CHECK (JSON_VALID(custom_fields))` is no longer valid. The new constraint name should be:

`CONSTRAINT json.table_name.custom_fields CHECK (JSON_VALID(custom_fields))`. This is true for all CONSTRAINT, not only JSON_VALID().
