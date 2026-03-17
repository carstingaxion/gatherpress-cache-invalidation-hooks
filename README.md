# GatherPress Cache Invalidation Hooks

**Contributors:**      carstenbach & WordPress Telex  
**Tags:**              gatherpress, cache, invalidation, wp-cron  
**Tested up to:**      6.8  
**Stable tag:**        0.3.0  
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

## Developer Documentation

Developers can extend the system through filters and actions, which are documented in [`docs/developer/hooks/Hooks.md`](docs/developer/hooks/Hooks.md).


### Testing

This plugin includes a full test suite using `wp-phpunit` and `wp-env`. Tests cover the scheduler's singleton pattern, hook registrations, cron scheduling, cache invalidation, and the optional upcoming events option tracker.

***Prerequisites***

* Node.js and npm (for `wp-env`)
* Docker (required by `wp-env`)
* Composer (for PHP test dependencies)

***Setup***

1. Install JavaScript dependencies:

`npm install`

2. Install PHP test dependencies:

`composer install`

3. Start the WordPress test environment:

`npx wp-env start`

***Running Tests***

Run the full test suite:

`npm run test:php`

Run only unit tests (singleton pattern, class structure, method signatures):

`npm run test:php:unit`

Run only integration tests (cron scheduling, cache invalidation, hook registration):

`npm run test:php:integration`

***Test Suites***

**Unit Tests** (`tests/unit/`)

* `SchedulerClassTest` — Validates the singleton pattern (private constructor, private clone, wakeup exception), verifies all public methods exist with correct signatures and return types, and confirms the class is declared as `final`.

**Integration Tests** (`tests/integration/`)

* `StatusTransitionTest` — Tests that publishing a GatherPress event schedules a cron job, unpublishing or trashing clears the cron job, past end dates are skipped, missing end dates are handled, and same-status transitions are ignored.
* `ClearScheduleTest` — Tests that `clear_schedule()` removes cron jobs for GatherPress events, ignores non-event post types, and handles non-existent posts and already-cleared schedules gracefully.
* `CacheInvalidationTest` — Tests that `invalidate_caches()` clears object cache entries, the `gatherpress_event_end_cache_keys` filter extends cache keys, non-array filter returns fall back to defaults, and the method is properly hooked to the event ended action.
* `UpcomingEventsOptionTrackerTest` — Tests that tracking is disabled by default, `remove_from_tracking()` cleans and re-indexes the option array, non-array and empty option states are handled gracefully, and `check_ended_events()` handles missing posts without errors.
* `HookRegistrationTest` — Verifies all WordPress hooks are registered at the correct priorities: `transition_post_status`, `gatherpress_event_ended`, `before_delete_post`, block init, scheduler init, and that the daily tracker cron is not scheduled when the feature is disabled.

***Writing New Tests***

Place unit tests in `tests/unit/` and integration tests in `tests/integration/`. All test files must end with `Test.php`. Extend `WP_UnitTestCase` for access to WordPress test factories and assertions. Use `set_up()` and `tear_down()` (not `setUp`/`tearDown`) for fixture management.

***Manual Testing Recommendations***

1. Create a test event ending in 5 minutes
2. Check scheduled cron jobs: `wp cron event list`
3. Wait for end time, verify caches cleared
4. Test unpublishing: ensure cron job removed
5. Test deletion: confirm cleanup runs


## Changelog

All notable changes to this project will be documented in the [CHANGELOG.md](CHANGELOG.md).
