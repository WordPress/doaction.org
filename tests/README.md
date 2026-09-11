# Security regression tests

Run against a disposable WordPress test database with PHP 8.0 or later, PHPUnit 9.6, the WordPress PHPUnit test library, and compatible PHPUnit polyfills:

```sh
WP_TESTS_DIR=/absolute/path/to/wordpress-tests-lib \
WP_TESTS_CONFIG_FILE_PATH=/absolute/path/to/wp-tests-config.php \
WP_TESTS_PHPUNIT_POLYFILLS_PATH=/absolute/path/to/phpunit-polyfills \
phpunit -c /absolute/path/to/doaction.org/phpunit.xml.dist
```

The WordPress test bootstrap recreates database tables. Never point the configuration at a live database. The tests load the plugin automatically and cover recipient authorization, nonprofit associations, native custom fields, and metadata escaping.

Run the repository-wide request and output checks with `phpcs --standard=/absolute/path/to/doaction.org/phpcs-security.xml.dist`. New and changed PHP code must also pass the full `WordPress` standard. Narrow inline exceptions cover read-only navigation and form markup whose individual values are already escaped.

The security fix records validated nonprofit selections in protected metadata. Existing cross-owner associations remain accessible to administrators, but an administrator must re-save the event's nonprofit selection to approve those associations for public signup. Historical metadata cannot establish whether an administrator or an unauthorized organiser created them.
