# DbpRelayCoreConnectorLdapBundle

[GitLab](https://gitlab.tugraz.at/dbp/relay/dbp-relay-core-connector-ldap-bundle) |
[Packagist](https://packagist.org/packages/dbp/relay-core-connector-ldap-bundle) |

The core_connector_ldap bundle provides an implementation of the `AuthorizationDataProviderInterface` which retrieves user authorization data from an LDAP server.

## Bundle installation

You can install the bundle directly from [packagist.org](https://packagist.org/packages/dbp/relay-core-connector-ldap-bundle).

```bash
composer require dbp/relay-core-connector-ldap-bundle
```

## Integration into the Relay API Server

* Add the bundle to your `config/bundles.php` in front of `DbpRelayCoreBundle`:

```php
...
Dbp\Relay\CoreConnectorLdapBundle\DbpRelayCoreConnectorLdapBundle::class => ['all' => true],
Dbp\Relay\CoreBundle\DbpRelayCoreBundle::class => ['all' => true],
];
```

If you were using the [DBP API Server Template](https://gitlab.tugraz.at/dbp/relay/dbp-relay-server-template)
as template for your Symfony application, then this should have already been generated for you.

* Run `composer install` to clear caches

## Configuration

The bundle has a `roles`, a `attributes` and a `ldap` configuration value that you can specify in your
app, either by hard-coding it, or by referencing an environment variable.

For this create `config/packages/dbp_relay_core_connector_ldap.yaml` in the app with the following
content:

```yaml
dbp_relay_core_connector_ldap:
  roles:
    - name: ROLE_LIBRARY_MANAGER
    - name: ROLE_LIBRARY_USER
  attributes:
    - name: LIBRARY_IDS
  ldap:
    host: '%env(LDAP_AUTH_CONNECTOR_LDAP_HOST)%'
    base_dn: '%env(LDAP_AUTH_CONNECTOR_LDAP_BASE_DN)%'
    username: '%env(LDAP_AUTH_CONNECTOR_LDAP_USER)%'
    password: '%env(LDAP_AUTH_CONNECTOR_LDAP_PASS)%'
    encryption: '%env(LDAP_AUTH_CONNECTOR_LDAP_ENCRYPTION)%'
    attributes:
      identifier: '%env(LDAP_AUTH_CONNECTOR_LDAP_ATTRIBUTE_IDENTIFIER)%'
```

If you were using the [DBP API Server Template](https://gitlab.tugraz.at/dbp/relay/dbp-relay-server-template)
as template for your Symfony application, then the configuration file should have already been generated for you.

For more info on bundle configuration see <https://symfony.com/doc/current/bundles/configuration.html>.

## Development & Testing

* Install dependencies: `composer install`
* Run tests: `composer test`
* Run linters: `composer run lint`
* Run cs-fixer: `composer run cs-fix`

## Bundle dependencies

Don't forget you need to pull down your dependencies in your main application if you are installing packages in a bundle.

```bash
# updates and installs dependencies of dbp/relay-core-connector-ldap-bundle
composer update dbp/relay-core-connector-ldap-bundle
```
