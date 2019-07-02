[titleEn]: <>(Added ReadProtected Flag)

We have added an `ReadProtected` Flag to the DAL.

You can mark fields with this flag, so that they won't be included in Api-Responses and can't be read from outside the system.
The flag allows you to define for which Api the restrictions should apply.

This makes the `Internal` flag obsolete, so we removed it.

### Examples
To protect read access to field for the SalesChannelApi use the flag like this:
```
(new StringField('test', 'test'))->addFlags(new ReadProtected(SalesChannelApiSource::class)),
```

The Example for protecting the read access for the AdminApi would look like this:
```
(new StringField('test', 'test'))->addFlags(new ReadProtected(AdminApiSource::class)),
```

### Breaking change

We've removed the `Internal` flag, because it can be represented with the new `ReadProtected` Flag.

##### Before
```
(new StringField('test', 'test'))->addFlags(new Internal()),
```

#### After 
```
(new StringField('test', 'test'))->addFlags(new ReadProtected(SalesChannelApiSource::class, AdminApiSource::class)),
```
