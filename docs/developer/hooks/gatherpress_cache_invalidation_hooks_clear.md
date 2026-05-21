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

- [includes/classes/class-cron-scheduler.php:130](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L130)
```php
do_action( 'gatherpress_cache_invalidation_hooks_clear', $post->ID, $post )
```

- [includes/classes/class-cron-scheduler.php:169](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L169)
```php
do_action( 'gatherpress_cache_invalidation_hooks_clear', $event->event->ID, $event->event )
```

- [includes/classes/class-cron-scheduler.php:187](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L187)
```php
do_action( 'gatherpress_cache_invalidation_hooks_clear', $event->event->ID, $event->event )
```



[← All Hooks](Hooks.md)
