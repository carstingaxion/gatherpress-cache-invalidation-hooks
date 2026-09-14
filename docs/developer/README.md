
# Developer Documentation

Developers can extend the system through filters and actions, which are documented in [`hooks/Hooks.md`](docs/developer/hooks/Hooks.md).


## Testing

This plugin includes a full test suite using `wp-phpunit` and `wp-env`. Tests cover the scheduler's singleton pattern, hook registrations, cron scheduling, cache invalidation, and the optional upcoming events option tracker.

### Prerequisites

* Node.js and npm (for `wp-env`)
* Docker (required by `wp-env`)
* Composer (for PHP test dependencies)

### Setup

1. Install JavaScript dependencies:

`npm install`

2. Install PHP test dependencies:

`composer install`

3. Start the WordPress test environment:

`npx wp-env start`

### Running Tests

Run the full test suite:

`npm run test:php`

Run only unit tests (singleton pattern, class structure, method signatures):

`npm run test:php:unit`

Run only integration tests (cron scheduling, cache invalidation, hook registration):

`npm run test:php:integration`

### Test Suites

#### Unit Tests (`tests/unit/`)

* `SchedulerClassTest` — Validates the singleton pattern (private constructor, private clone, wakeup exception), verifies all public methods exist with correct signatures and return types, and confirms the class is declared as `final`.

#### Integration Tests (`tests/integration/`)

* `StatusTransitionTest` — Tests that publishing a GatherPress event schedules a cron job, unpublishing or trashing clears the cron job, past end dates are skipped, missing end dates are handled, and same-status transitions are ignored.
* `ClearScheduleTest` — Tests that `clear_schedule()` removes cron jobs for GatherPress events, ignores non-event post types, and handles non-existent posts and already-cleared schedules gracefully.
* `CacheInvalidationTest` — Tests that `invalidate_caches()` clears object cache entries, the `gatherpress_event_end_cache_keys` filter extends cache keys, non-array filter returns fall back to defaults, and the method is properly hooked to the event ended action.
* `UpcomingEventsOptionTrackerTest` — Tests that tracking is disabled by default, `remove_from_tracking()` cleans and re-indexes the option array, non-array and empty option states are handled gracefully, and `check_ended_events()` handles missing posts without errors.
* `HookRegistrationTest` — Verifies all WordPress hooks are registered at the correct priorities: `transition_post_status`, `gatherpress_event_ended`, `before_delete_post`, block init, scheduler init, and that the daily tracker cron is not scheduled when the feature is disabled.

### Writing New Tests

Place unit tests in `tests/unit/` and integration tests in `tests/integration/`. All test files must end with `Test.php`. Extend `WP_UnitTestCase` for access to WordPress test factories and assertions. Use `set_up()` and `tear_down()` (not `setUp`/`tearDown`) for fixture management.

### Manual Testing Recommendations

1. Create a test event ending in 5 minutes
2. Check scheduled cron jobs: `wp cron event list`
3. Wait for end time, verify caches cleared
4. Test unpublishing: ensure cron job removed
5. Test deletion: confirm cleanup runs

