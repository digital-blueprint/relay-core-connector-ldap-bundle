# Overview

The Core Connector LDAP provides an _Authorization Data Provider_, which retrieves user attributes
used for access control from an LDAP server.

## Configuration

```yaml
    dbp_relay_core_connector_ldap:
      ldap_connection: '%env(LDAP_CONNECTION)%'
      attributes:
        - name: functions
          ldap_attribute: USER-FUNCTIONS
          is_array: true # default: false
        - name: ...
```

The ```ldap_connection``` node specifies which LDAP connection to use (see ```dbp/relay-ldap-bundle```).
The ```attributes``` node defines a mapping between LDAP (i.e. source) user attributes and access control (i.e. target) 
user attributes:

* ```name``` The name of the target user attribute
* ```ldap_attribute```The name of the source user attribute
* ```is_array``` (default value: ```false```) Used to specify whether the user attribute is of array or scalar type.
If the array type of the source and target attribute do not match, ```ldap_attribute``` is converted to/from array.