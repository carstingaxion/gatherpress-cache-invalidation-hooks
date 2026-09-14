# gatherpress_cache_invalidation_hooks_new_upcoming

## Auto-generated Example

```php
add_action(
   'gatherpress_cache_invalidation_hooks_new_upcoming',
    function(
        $ID,
        $post = null
    ) {
        // Your code here.
    }
);
```

## Parameters

- `$ID` Other variable names: `$object_id`
- `$post`

## Files

- [includes/classes/class-cron-scheduler.php:130](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L130)
```php
do_action( 'gatherpress_cache_invalidation_hooks_new_upcoming', $post->ID, $post )
```

- [includes/classes/class-cron-scheduler.php:168](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L168)
```php
do_action( 'gatherpress_cache_invalidation_hooks_new_upcoming', $object_id )
```



[← All Hooks](Hooks.md)
