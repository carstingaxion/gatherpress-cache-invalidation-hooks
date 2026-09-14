# gatherpress_event_ended


Trigger the main event end action hook.

Central hook for event end processing.
All cleanup operations (cache invalidation, tracking removal, etc.)
are hooked to this action at various priorities.

## Example

```php
// Send email when events end
add_action( 'gatherpress_event_ended', function( $event_id ) {
    // Your custom logic here
    delete_transient( "my_event_data_{$event_id}" );
    wp_mail( 'admin@example.com', 'Event Ended', "Event {$event_id} has concluded." );
}, 10, 2 );
```

## Parameters

- *`int`* `$event_id` The post ID of the event that ended.

## Files

- [includes/classes/class-cron-scheduler.php:229](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-cron-scheduler.php#L229)
```php
do_action( 'gatherpress_event_ended', $event_id )
```



[← All Hooks](Hooks.md)
