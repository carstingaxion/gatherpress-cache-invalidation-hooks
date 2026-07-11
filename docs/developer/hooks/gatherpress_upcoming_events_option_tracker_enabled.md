# gatherpress_upcoming_events_option_tracker_enabled

> **DEPRECATED**
> This hook was deprecated in version 0.2.0.

## Auto-generated Example

```php
add_filter(
   'gatherpress_upcoming_events_option_tracker_enabled',
    function(
        array $array,
        string $0_2_0,
        $sprintf_gatherpress_upcoming_tracker_enabled_post_type__upcoming_tracker_enabled
    ) {
        // Your code here.
        return $array;
    },
    10,
    3
);
```

## Parameters

- *`array`* `$array`
- *`string`* `$0_2_0`
- `$sprintf_gatherpress_upcoming_tracker_enabled_post_type__upcoming_tracker_enabled`

## Files

- [includes/classes/class-option-tracker.php:128](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-option-tracker.php#L128)
```php
apply_filters_deprecated(
				'gatherpress_upcoming_events_option_tracker_enabled',
				array( false ),
				'0.2.0',
				sprintf(
					/* translators: 1: new general filter name, 2: per-type filter pattern */
					__(
						'Use "%1$s" to enable all post types, or "%2$s" for a specific post type.',
						'gatherpress-cache-invalidation-hooks'
					),
					'gatherpress_upcoming_tracker_enabled',
					'{post_type}_upcoming_tracker_enabled'
				)
			)
```



[← All Hooks](Hooks.md)
