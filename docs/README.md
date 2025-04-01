# Overview

The Core Connector LDAP provides an _Authorization Data Provider_, which retrieves user attributes
used for access control from an LDAP server.

## Configuration

```yaml
    dbp_relay_core_connector_ldap:
      connections:
        my-connection:
          host: '%env(LDAP_HOST)%'
          base_dn: '%env(LDAP_BASE_DN)%'
          username: '%env(LDAP_USER)%'
          password: '%env(LDAP_PASS)%'
      user_attribute_provider:
        ldap_connection: 'my-connection'
        attributes:
          - name: functions
            ldap_attribute: USER-FUNCTIONS
            is_array: true # default: false
          - name: ...
```

### User Attribute Provider

The ```ldap_connection``` node specifies which LDAP connection to use.
The ```attributes``` node defines a mapping between LDAP (i.e. source) user attributes and access control (i.e. target) 
user attributes:

* ```name``` The name of the target user attribute
* ```ldap_attribute```The name of the source user attribute
* ```is_array``` (default value: ```false```) Used to specify whether the user attribute is of array or scalar type.
If the array type of the source and target attribute do not match, ```ldap_attribute``` is converted to/from array.