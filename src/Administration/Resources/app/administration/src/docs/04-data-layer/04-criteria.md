# Criteria (Skeleton)

- Role: Express query intent for repository `search`
- Components:
  - Filters (equals, range, multi, contains)
  - Sorting definitions
  - Associations / nested criteria
  - Pagination (page, limit, total-count mode)
- Server translation: Maps to DAL search API parameters
- Performance considerations:
  - Limit associations to necessary depth
  - Use total-count selectively (impacts performance)
- Extension possibilities: custom filters / aggregations (placeholder)
