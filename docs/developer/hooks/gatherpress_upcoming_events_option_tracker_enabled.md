# gatherpress_upcoming_events_option_tracker_enabled


Filter whether to enable the upcoming events option tracker tracking system.

The upcoming events option tracker provides redundant tracking of upcoming events and a daily
cron job to catch any events whose scheduled cron jobs failed. This adds
database writes on every event status change, so it's disabled by default.

Enable for high-value deployments where missing an event cleanup would be critical.

## Example

```php
// Enable upcoming events option tracker
add_filter( 'gatherpress_upcoming_events_option_tracker_enabled', '__return_true' );
```

## Parameters

- *`bool`* `$enabled` Whether upcoming events option tracker is enabled. Default false.

## Files

- [includes/classes/class-option-tracker.php:133](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/blob/main/includes/classes/class-option-tracker.php#L133)
```php
apply_filters( 'gatherpress_upcoming_events_option_tracker_enabled', false )
```



[← All Hooks](Hooks.md)
