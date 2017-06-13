# f3-ldap
**LDAP Plugin for the PHP Fat-Free Framework**

This plugin is build for [Fat-Free Framework](http://www.fatfreeframework.com/). To learn more about Fat-Free Framework, visit their Homepage or the [F3 Git Repository](http://github.com/bcosca/fatfree)

* [Installation](#installation)
* [Usage](#usage)
* [Connections Options](#connections-options)
* [Query Options](#query-options)
* [F3 Auth Class](#f3-auth-class)
* [LDAP Class methods](#ldap-class-methods)
* [Mapper Class methods](#mapper-class-methods)


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
LDAP_OPT_REFERRALS=0
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

## F3 Auth Class

The Auth Class of F3 uses his own LDAP-Settings and is currently not compatible to f3-ldap.
`$auth->login` does a ldap-search for `uid=$id` and tries to bind this entry with the given `$pw`

Usage:
```php
$auth = new \Auth ('ldap', [
                                'dc'        => $f3->get('ldap.HOST'),
                                'base_dn'   => $f3->get('ldap.BASEDN'),
                                'rdn'       => $f3->get('ldap.USERNAME'),
                                'pw'        => $f3->get('ldap.PASSWORD')
                           ] 
                  );
if ($auth->login($id, $pw) {
    // login successful
}
```

## LDAP Class Methods

### Connecting
```php
// Set LDAP Configuration and connect
function connect(string $ldaphoststring=NULL) : LDAP;
// Disconnect from LDAP 
function disconnect() : bool;
// Bind using Credentials in Config or parameters
function bind(string $username=NULL, string $password=NULL) : LDAP;
// Try to bind using given credentials (useful for authchecking)
function tryBind(string $username, string $password) : bool;
// Returns true if a valid connection is established
function isConnected() : bool;
// Returns the currently authenticated User
function getAuthUser($getDN=false, int $ttl=0) : string;
```

### Settings
```php
// Get LDAP Option
function getLDAPOption(int $option) : int;
// Set LDAP Option
function setLDAPOption(int $option, $value) : bool;
// Set LDAP Options
function setLDAPOptions(array $options) : bool;
// Set Base DN
function setBaseDN(string $baseDN) : LDAP;
// Get Base DN
function getBaseDN() : string;
// Set custom Authentication Attributes (defaults: mail, cn, uid)
function setAuthAttributes(array $authAttributes) : LDAP;
// Get Authentication Attributes
function getAuthAttributes() : array;
```

### Search
```php
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
```

### Modification
```php
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
```

### Other
```php
// Get Error Message/Error Code
function getError($errCode=false) : string;
```

## Mapper Class Methods

The Mapper extends https://github.com/bcosca/fatfree-core/blob/master/db/cursor.php
and has more methods than those defined here

### Data Mapping
```php
// Return the fields of the mapper object as an associative array
function cast($obj = NULL) : array;
// Clear given attribute
function clear($attribute);
// Reset changed Data info
function clearChanges();
// Get changed Data in this object
function getChanges() : array;
// Hydrate mapper object using hive array variable
function copyfrom($var,$func=NULL);
// Populate hive array variable with mapper fields
function copyto($key);
// Return DB Type ('LDAP')
function dbtype() : string;
// Check if given Attribute exists
function exists($attribute) : bool;
// Return Attribute Names
function fields() : array;
// Set Value of Attribute
function set($attribute, $val);
// Get Value of Attribute
function &get($attribute) : mixed;
// Retrieve external iterator for fields
function getIterator() : ArrayIterator;
// Reset Object
function reset();
// Return record at specified offset using criteria of previous
function skip($ofs=1) : array;
```

### Search
```php
// Start a Search
function find($filter=NULL, array $options=NULL, $ttl=0) : array;
// Count results
function count($filter=NULL, array $options=NULL, $ttl=0) : int;
```

### Modification
```php
// Insert new record
function insert() : bool;
// Update current record
function update() : bool;
// Erase mapped entry/entries
function erase(string $filter=NULL) : bool;
// Move current entry to new parent
function moveTo(string $newParentDN) : bool;
```
