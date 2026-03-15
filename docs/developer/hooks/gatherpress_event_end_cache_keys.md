# gatherpress_event_end_cache_keys


Filter cache keys to invalidate when an event ends.

## Example

```php
// Extend cache keys to invalidate
add_filter( 'gatherpress_event_end_cache_keys', function( $keys, $event_id ) {
    $keys[] = 'my_custom_cache_key';
    $keys[] = "event_category_{$event_id}";
    return $keys;
}, 10, 2 );
```

## Parameters

- *`array<string>`* `$cache_keys` Array of cache keys to invalidate.
- *`int`* `$event_id` The event ID.

## Files

- [includes/classes/class-cron-scheduler.php:281](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L281)
```php
apply_filters(
				'gatherpress_event_end_cache_keys',
				$default_keys,
				$event_id
			)
```



[← All Hooks](Hooks.md)
