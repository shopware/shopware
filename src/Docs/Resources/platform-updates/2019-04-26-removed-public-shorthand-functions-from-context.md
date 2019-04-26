[titleEn]: <>(Removed public shorthand functions of context)

The context used to have the shorthand functions `getUserId` and `getSalesChannelId`.

The problem is that it depended on the Source of the Context whether these Ids were set or not.
Especially in the case of the `salesChannelId` this was problematic, because it silently returned the DefaultId in case the source didn't match.

We removed the shorthand functions, so noe you have to take care of checking the ContextSource:
```php
if (!$context->getSource() instanceof AdminApiSource) {
	throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
}
```

We have added the `InvalidContextSourceException` for the case that a different CntextSource was expected.