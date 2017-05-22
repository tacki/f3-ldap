# f3-ldap
**LDAP Plugin for the PHP Fat-Free Framework**

This plugin is build for [Fat-Free Framework](http://www.fatfreeframework.com/). To learn more about Fat-Free Framework, visit their Homepage or the [F3 Git Repository](http://github.com/bcosca/fatfree)

* [Installation](#installation)
* [Usage](#usage)

## Installation

To install this plugin, just copy the `lib/LDAP.php` and the `lib/LDAP/Mapper.php` file into your F3 `lib/` or your `AUTOLOAD` folder.

## Usage

This Plugin consists of two classes. `LDAP` is a simple Wrapper for the standard PHP ldap functions and `LDAP/Mapper` is a class to handle the LDAP-Server like any other Database-Mapper in F3. 

Some examples how to use the basic LDAP-class:

```php
$ldap = new Library\LDAP(
                         'ldap://myldaphost.example.com',
                         'cn=example,cn=com',
                         'username',
                         'password' 
                        );

# Search for all entries in ou=users with an uid
$users = $ldap->search('ou=users,cn=example,cn=com', '(uid=*)')->getAll();

# Search for a single User and retrieve the first result
$tim = $ldap->search(NULL, '(uid=tim)')->getFirstEntry();

# Ouput the Common Name of Tim
echo $tim['cn']

# Delete User Tim
$ldap->erase($tim['distinguishedName']);
```

How to do the same using the LDAP Mapper:

```php
$ldap = new Library\LDAP(
                         'ldap://myldaphost.example.com',
                         'cn=example,cn=com',
                         'username',
                         'password' 
                        );

# Search for all entries in ou=users with an uid
$mapper = new Library\LDAP\Mapper($ldap);
$mapper->setBaseDN('ou=users,cn=example,cn=com');
$users = $mapper->find('(uid=*)');

# Search for a single User and retrieve the first result
$tim = new Library\LDAP\Mapper($ldap);
$tim->load('(uid=tim)');

# Ouput the Common Name of Tim
echo $tim->cn

# Delete User Tim
$tim->erase();
```

The LDAP connection Options can also be defined inside a f3 config file and don't have to be defined in your source:

```ini
[ldap]
HOST="ldap://myldaphost.example.com"
BASEDN="cn=example,cn=com"
USERNAME="username"
PASSWORD="password"

[ldap.options]
LDAP_OPT_PROTOCOL_VERSION=3
LDAP_OPT_DEREF=0
...
```
