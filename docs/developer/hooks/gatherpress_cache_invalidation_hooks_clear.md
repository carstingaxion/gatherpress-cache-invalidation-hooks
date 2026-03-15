# gatherpress_cache_invalidation_hooks_clear

## Auto-generated Example

```php
add_action(
   'gatherpress_cache_invalidation_hooks_clear',
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

- [includes/classes/class-cron-scheduler.php:138](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L138)
```php
do_action( 'gatherpress_cache_invalidation_hooks_clear', $post->ID, $post )
```

- [includes/classes/class-cron-scheduler.php:177](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L177)
```php
do_action( 'gatherpress_cache_invalidation_hooks_clear', $event->event->ID, $event->event )
```

- [includes/classes/class-cron-scheduler.php:195](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L195)
```php
do_action( 'gatherpress_cache_invalidation_hooks_clear', $event->event->ID, $event->event )
```



[← All Hooks](Hooks.md)
