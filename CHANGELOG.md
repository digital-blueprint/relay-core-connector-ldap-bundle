# Changelog

## Unreleased

- Fix pagination when no sorting is applied
- Make the user identifier which is used for the user attribute lookup configurable by an authorization attribute expression
(`current_ldap_user_identifier_expression`)
- Enhance test tools, allowing to expect a requested `cn` value or pass a callback function to check if the query
is as expected
- simplify filter tree being passed to LDAP

## v0.2.17

- Update core and adapt

## v0.2.16

* Update core and adapt

## v0.2.15

* Fix empty-test for string filter values

## v0.2.14

* Pre-check filter values for correct types to avoid internal ldap library errors

## v0.2.13

* Add 'result_items_will_sort_limit' to LDAP config, i.e., the maximum number of items that will be sorted without throwing an error.
  This is to prevent uncontrolled out-of-memory errors when sorting a large number of results sets. (default: 10 000)

## v0.2.12

* Dependency cleanups

## v0.2.11

* Drop support for PHP 8.1
* Drop support for api-platform 3.2/3.3
* Drop support for Symfony 5
* config: allow specifying connection identifiers as keys in the `connections` configuration

## v0.2.10

* Re-Allow getting user attributes for debug purposes (without user session)

## v0.2.9

* Provide TestLdapConnectionProvider for other bundles to mock LDAP responses

## v0.2.8

* Add mock tests for ldap connection provider and user attribute provider
* Allow use attribute lookup for users other than the logged-in user

## v0.2.7

* Update core

## v0.2.6

* Adapt to user identifier now being none-null even for system account users

## v0.2.5

* Require higher version of illuminate/collections

## v0.2.4

* Add various missing dependencies
* Port to PHPUnit 10

## v0.2.3

* Add support for sort by multiple attributes

## v0.2.2

* Add support for api-platform 3.2

## v0.1.7

* Add support for symfony/event-dispatcher-contracts v3
* Add support for Symfony 6

## v0.1.6

* Drop support for PHP 7.4/8.0

## v0.1.5

* Drop support for PHP 7.3

## v0.1.3

* Use the global "cache.app" adapter for caching instead of always using the filesystem adapter

## v0.1.2

* Update to api-platform 2.7
* New config key 'ldap_attribute'
