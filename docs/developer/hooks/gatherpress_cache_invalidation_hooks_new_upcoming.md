# gatherpress_cache_invalidation_hooks_new_upcoming

## Auto-generated Example

```php
add_action(
   'gatherpress_cache_invalidation_hooks_new_upcoming',
    function(
        $ID,
        $post
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- `$ID` Other variable names: `$event->ID`
- `$post` Other variable names: `$event`

## Files

- [includes/classes/class-cron-scheduler.php:125](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L125)
```php
do_action( 'gatherpress_cache_invalidation_hooks_new_upcoming', $post->ID, $post )
```

- [includes/classes/class-cron-scheduler.php:164](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L164)
```php
do_action( 'gatherpress_cache_invalidation_hooks_new_upcoming', $event->event->ID, $event->event )
```



[← All Hooks](Hooks.md)
