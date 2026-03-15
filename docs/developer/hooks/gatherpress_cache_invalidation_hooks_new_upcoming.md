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

- [includes/classes/class-cron-scheduler.php:133](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L133)
```php
do_action( 'gatherpress_cache_invalidation_hooks_new_upcoming', $post->ID, $post )
```

- [includes/classes/class-cron-scheduler.php:172](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L172)
```php
do_action( 'gatherpress_cache_invalidation_hooks_new_upcoming', $event->event->ID, $event->event )
```



[← All Hooks](Hooks.md)
