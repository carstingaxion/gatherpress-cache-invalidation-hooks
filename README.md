# GatherPress Cache Invalidation Hooks

**Contributors:**      carstenbach & WordPress Telex  
**Tags:**              gatherpress, cache, invalidation, wp-cron  
**Tested up to:**      6.8  
**Stable tag:**        0.5.0  
**License:**           GPLv2 or later  
**License URI:**       [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)  

Cache Invalidation system based on event end dates, similar to WordPress scheduled posts, but for GatherPress.

[![Build, test & measure](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/actions/workflows/build-test-measure.yml/badge.svg?branch=main)](https://github.com/carstingaxion/gatherpress-cache-invalidation-hooks/actions/workflows/build-test-measure.yml)

---

## Description

The "GatherPress Cache Invalidation Hooks" plugin is an event-driven system that automatically executes actions when GatherPress events reach their end time. Think of it as WordPress's scheduled post system, but instead of publishing posts at a future date, it triggers cleanup tasks (cache invalidation, notifications, etc.) when events conclude.

## Installation

1. Upload the plugin files to `/wp-content/plugins/gatherpress-cache-invalidation-hooks`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. The scheduler automatically activates if GatherPress is installed

## Frequently Asked Questions

### Why Was It Built?

GatherPress events have a lifecycle: they're upcoming, then active, then past. Each transition affects what data should be cached and displayed:

* Upcoming events appear in "upcoming" lists and individual event pages
* Past events should move to "past" lists and trigger cache refreshes
* Without automated cleanup, stale data persists in caches, showing ended events as still upcoming

Manual cache clearing isn't scalable. The scheduler automates this process, ensuring accurate, timely data across your site.

### How Does It Work?

The system operates in four phases:

1. **Event Publication Detection**
   * Hooks into `transition_post_status` to detect when events are published
   * Reads the event's end date from post meta
   * Schedules a WordPress cron job for that specific timestamp

2. **End Time Execution**
   * WordPress cron triggers the scheduled job at the event's end time
   * System validates the event has actually ended (using GatherPress's own validation)
   * **Fires the `gatherpress_event_ended` action hook**

3. **Cleanup Chain**
   * Cache invalidation runs (object cache + post cache)
   * Optional: Upcoming events option tracker cleanup (if enabled)

4. **Status Change Handling**
   * If event is unpublished (draft, trash or delete), scheduled job is cancelled


### Does this work only with GatherPress events?

Yes!

### What is the "upcoming events option tracker" feature?

The upcoming events option tracker is an optional redundancy system:

* Stores all upcoming event IDs in a wp_option named `gatherpress_upcoming_events`. This option is a technical need for the feature to work, but it can be re-used for other purposes, like speeding up queries for upcoming events.
* Runs a daily cron to check if any events ended but weren't processed
* Catches edge cases where scheduled cron jobs fail
* Disabled by default to minimize database writes
* The developer section has a code example for how to enable it.


### Can the Upcoming Events Option be used in Queries?

When the upcoming events option tracker is enabled, you can use it to efficiently query upcoming events:

```php
/**
 * Filter event queries to only show tracked upcoming events.
 * This provides a performance boost by limiting queries to known upcoming events.
 */
add_action( 'pre_get_posts', function( $query ) {
    // The DB option will only be available, if this filter is enabled.
    if ( true !== apply_filters( 'gatherpress_upcoming_events_option_tracker_enabled', false ) ) {
        return;
    }
    if (
        ! isset( $query->query_vars['gatherpress_event_query'] ) ||
        'upcoming' !== $query->query_vars['gatherpress_event_query']
    ) {
        return;
    }
    // Get tracked upcoming event IDs
    $upcoming_ids = get_option( 'gatherpress_upcoming_events', array() );
    if ( ! empty( $upcoming_ids ) && is_array( $upcoming_ids ) ) {
        // Limit query to only upcoming events
        $query->set( 'post__in', $upcoming_ids );
    }
} );
```

## Changelog

All notable changes to this project will be documented in the [CHANGELOG.md](CHANGELOG.md).
