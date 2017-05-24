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
function connect(string $ldaphoststring=NULL) : LDAP;
// Disconnect from LDAP 
function disconnect() : bool;
// Try to bind using Credentials in Config or parameters
function bind(string $username=NULL, string $password=NULL) : LDAP;

/*
    Settings Functions
*/
// Get LDAP Option
function getLDAPOption(int $option) : int;
// Set LDAP Option
function setLDAPOption(int $option, $value) : bool;
// Set LDAP Options
function setLDAPOptions(array $options) : bool;
// Set Base DN
function setBaseDN(string $baseDN) : LDAP;

/*
    Search+Result Functions
*/
// Start a search
function search(string $searchdn=NULL, 
                string $filter='(objectclass=*)', 
                array $attributes=array(), 
                $scope=LDAP::SCOPE_SUBTREE, 
                $attrsonly=0, 
                $sizelimit=0, 
                $timelimit=0, 
                $deref=LDAP_DEREF_NEVER) : LDAP;
// Free results
function free() : bool;
// Count results in searchresult
function count(int $ttl=0) : int;
// Get all searchresults
function getAll(int $ttl=0) : array;
// Retrieve first Entry from searchresult
function getFirstEntry(int $ttl=0) : array;
// Set pointer position to first entry in searchresult
function first() : LDAP;
// Move pointer to next entry
function next() : LDAP;
// Retrieve Entry at current pointer position
function getEntry(int $ttl=0) : array;
// Get Name of the first Attribute in current Entry
function getFirstAttribute() : string;
// Move pointer to next Attribute and retrieve
function getNextAttribute() : string;
// Retrieve all references in current searchresult
function getAllReferences() : array;

/*
    Modification Functions
*/
// Add Entry at given dn
function add(string $dn, array $entry) : bool;
// Save changes to dn
function save(string $dn, array $changes) : bool;
// Rename a dn 
function rename(string $dn, string $newrdn) : bool;
// Move dn to another parent
function move(string $dn, string $newparent) : bool;
// Remove DN
function erase(string $dn) : bool;

/*
    Other Functions
*/
// Get Error Message/Error Code
function getError($errCode=false) : string;
```
