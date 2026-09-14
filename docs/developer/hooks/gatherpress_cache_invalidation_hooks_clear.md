# gatherpress_cache_invalidation_hooks_clear

## Auto-generated Example

```php
add_action(
   'gatherpress_cache_invalidation_hooks_clear',
    function(
        $ID,
        $post = null
    ) {
        // Your code here.
    }
);
```

## Parameters

- `$ID` Other variable names: `$object_id`, `$post_id`
- `$post`

## Files

- [includes/classes/class-cron-scheduler.php:135](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L135)
```php
do_action( 'gatherpress_cache_invalidation_hooks_clear', $post->ID, $post )
```

- [includes/classes/class-cron-scheduler.php:171](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L171)
```php
do_action( 'gatherpress_cache_invalidation_hooks_clear', $object_id )
```

- [includes/classes/class-cron-scheduler.php:186](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L186)
```php
do_action( 'gatherpress_cache_invalidation_hooks_clear', $post_id )
```



[← All Hooks](Hooks.md)
