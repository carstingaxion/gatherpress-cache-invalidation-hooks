# gatherpress_upcoming_tracker_enabled


Filter whether to enable the tracker for all supporting post types at once.

When true, every post type that declares `gatherpress-event-date` support
gets its own tracking option and is included in the daily cron check.

## Example

```php
add_filter( 'gatherpress_upcoming_tracker_enabled', '__return_true' );
```

## Parameters

- *`bool`* `$enabled` Whether the tracker is globally enabled. Default false.
- *`string`* `$post_type` The post type currently being evaluated.

## Files

- [includes/classes/class-option-tracker.php:161](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-option-tracker.php#L161)
```php
apply_filters( 'gatherpress_upcoming_tracker_enabled', false, $post_type )
```



[← All Hooks](Hooks.md)
