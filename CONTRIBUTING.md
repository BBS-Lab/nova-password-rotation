# Contributing

Contributions are welcome. To keep the package green:

1. Fork and branch from `main`.
2. Run the quality gate before opening a PR:
   ```bash
   composer format         # Pint (laravel preset + strict types)
   composer analyse        # PHPStan level 8, empty baseline
   composer test-coverage  # Pest, 100% line coverage on src/
   ```
3. Every PHP file starts with `<?php`, a blank line, then `declare(strict_types=1);`.
4. No `final` classes (an arch test forbids them).
5. Support both Nova 4 and Nova 5 — avoid Nova-5-only APIs.
6. Add tests and update the [CHANGELOG](CHANGELOG.md) under `Unreleased`.

## Security

Please email `paris@big-boss-studio.com` for security issues instead of the issue tracker.
