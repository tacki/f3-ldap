# f3-ldap
**LDAP Plugin for the PHP Fat-Free Framework**

This plugin is build for [Fat-Free Framework](http://www.fatfreeframework.com/). To learn more about Fat-Free Framework, visit their Homepage or the [F3 Git Repository](http://github.com/bcosca/fatfree)

* [Installation](#installation)
* [Usage](#usage)
* [Connections Options](#connections-options)
* [Query Options](#query-options)
* [LDAP Class methos](#ldap-class-methods)

## Installation

To install this plugin, just copy the `lib/LDAP.php` and the `lib/LDAP` folder into your F3 `lib/` or your `AUTOLOAD` folder. This plugin requires PHP7.

## Usage

This Plugin consists of two classes. `LDAP` is a simple Wrapper for the standard PHP ldap functions and `LDAP/Mapper` is a class to handle the LDAP-Server like any other Database-Mapper in F3. 

Some examples how to use the basic LDAP-class:

```php
$ldap = new LDAP(
                    'ldap://myldaphost.example.com',
                    'cn=example,cn=com',
                    'username',
                    'password',
                    array(
                        LDAP_OPT_PROTOCOL_VERSION=>3,                          
                        LDAP_OPT_REFERRALS=>0,
                    )
        );

// Search for all entries in ou=users with an uid
$users = $ldap->search('ou=users,cn=example,cn=com', '(uid=*)')->getAll();

// Search for a single User and retrieve the first result
$tim = $ldap->search(NULL, '(uid=tim)')->getFirstEntry();

// Ouput the Common Name of Tim
echo $tim['cn']

// Delete User Tim
$ldap->erase($tim['distinguishedName']);
```

How to do the same using the LDAP Mapper:

```php
$ldap = new LDAP(
                    'ldap://myldaphost.example.com',
                    'cn=example,cn=com',
                    'username',
                    'password',
                    array(
                        LDAP_OPT_PROTOCOL_VERSION=>3,                          
                        LDAP_OPT_REFERRALS=>0,
                    )
        );

// Search for all entries in ou=users with an uid
$mapper = new LDAP\Mapper($ldap, 'ou=users,cn=example,cn=com');
$users = $mapper->find('(uid=*)');

// Search for a single User and retrieve the first result
$tim = new LDAP\Mapper($ldap);
$tim->load('(uid=tim)', ['limit'=>1]);
// or
$mapper = new LDAP\Mapper($ldap);
$tim = $mapper->findone('(uid=tim'));

// Ouput the Common Name of Tim
echo $tim->cn

// Delete User Tim
$tim->erase();
```

## Connections Options

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

## Query Options

The Mapper methods `load()`,`find()`,`findone()` and `count()` use a options array as second argument. These are the possible options:

```php
$options['attributes']  // retrieve only given attributes. Default: array() (all attributes)
$options['scope']       // searchscope. Default: LDAP::SCOPE_SUBTREE (subtree search)
$options['attronly']    // retrieve only the attributenames. Default: 0 (disabled)
$options['limit']       // limit to x results. Default: 0 (no limit)
$options['timelimit']   // limit timelimit to x seconds. Default: 0 (no limit)
$options['deref']       // specify how aliases should be handled. Default: LDAP_DEREF_NEVER (no dereferencing)
```

## LDAP Class Methods

```php
/*
    Connection Functions
*/
// Set LDAP Configuration and connect
public function connect(string $ldaphoststring=NULL);
// Disconnect from LDAP 
public function disconnect();
// Try to bind using Credentials in Config or parameters
bind(string $username=NULL, string $password=NULL);

/*
    Settings Functions
*/
// Get LDAP Option
public function getLDAPOption(int $option);
// Set LDAP Option
public function setLDAPOption(int $option, $value);
// Set LDAP Options
setLDAPOptions(array $options);
// Set Base DN
setBaseDN(string $baseDN);

/*
    Search+Result Functions
*/
// Start a search
search(string $searchdn=NULL, string $filter='(objectclass=*)', 
       array $attributes=array(), $scope=SELF::SCOPE_SUBTREE, 
       $attrsonly=0, $sizelimit=0, $timelimit=0, $deref=LDAP_DEREF_NEVER);
// Free results
free();
// Count results in searchresult
count(int $ttl=0);
// Get all searchresults
getAll(int $ttl=0);
// Retrieve first Entry from searchresult
getFirstEntry(int $ttl=0);
// Set pointer position to first entry in searchresult
function first();
// Move pointer to next entry
function next();
// Retrieve Entry at current pointer position
getEntry(int $ttl=0);
// Get Name of the first Attribute in current Entry
function getFirstAttribute();
// Move pointer to next Attribute
function getNextAttribute();
// Retrieve all references in current searchresult
getAllReferences();

/*
    Modification Functions
*/
// Add Entry at given dn
add(string $dn, array $entry);
// Save changes to dn
save(string $dn, array $changes);
// Rename a dn 
rename(string $dn, string $newrdn);
// Move dn to another parent
move(string $dn, string $newparent);
// Remove DN
erase(string $dn);

/*
    Other Functions
*/
// Get Error Message/Error Code
getError($errCode=false);
```
