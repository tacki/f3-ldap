<?php
/*
 * Copyright (C) 2017 Markus Schlegel <tacki@posteo.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class LDAP extends \Prefab
{
    const SCOPE_BASE = 1;
    const SCOPE_ONELEVEL = 2;
    const SCOPE_SUBTREE = 3;    
    
    /**   
     * @var resource before 8.1
     * @var LDAP\Connection after 8.1
     */
    protected $ldap;
    
    /**     
     * @var string
     */
    protected $host;
    
    /**   
     * @var string 
     */
    protected $baseDN;    
    
    /**
     * @var string
     */
    protected $authUser;
        
    /**
     * @var string  
     */
    protected $searchparams;    
    
    /**
     * @var resource  before 8.1
     * @var LDAP\Result after 8.1
     */
    protected $searchResult;
    
    /**
     * @var resource  before 8.1
     * @var LDAP\ResultEntry after 8.1
     */
    protected $curEntry;    
    
    /**
     * @var array
     */
    protected $authAttributes=['mail','uid','cn'];
    
    /**
     * Constructor
     * @param string $ldaphoststring
     * @param string %basedn
     * @param string $username
     * @param string $password
     * @param array $options
     */
    public function __construct($ldaphoststring=NULL, $basedn=NULL, $username=NULL, $password=NULL, array $options=array())
    {
        $f3 = \Base::instance();        
        
        if ($ldaphoststring) {                    
            $this->setLDAPOptions($options);            
            $this->connect($ldaphoststring);
            $this->bind($username, $password);
            #
            $this->baseDN = $basedn;
            $this->host = $ldaphoststring;
        } else {
            if($f3->get('ldap.HOST'))  {                 
                $this->setLDAPOptions((array)$f3->get('ldap.options'));
                $this->connect($f3->get('ldap.HOST'));
                $this->host = $f3->get('ldap.HOST');
                $this->baseDN = $f3->get('ldap.BASEDN');
                
                if ($f3->get('ldap.USERNAME')) {
                    $this->bind($f3->get('ldap.USERNAME'), $f3->get('ldap.PASSWORD'));
                }
            }
        }
    }
    
    /**
     * Set LDAP Configuration and connect
     * @param string $ldaphoststring
     * @return $this
     */
    public function connect($ldaphoststring=NULL)
    {
        $f3 = \Base::instance();
        
        $host = $ldaphoststring?$ldaphoststring:$f3->get('ldap.HOST');        
        
        $this->ldap   = ldap_connect($host);  
       
        return $this;
    }
    
    /**
     * Disconnect from LDAP 
     * @return bool
     */
    public function disconnect()
    {
        return ldap_unbind($this->ldap);
    }
    
    /**
     * Get LDAP Option
     * @param int $option
     * @return int
     */
    public function getLDAPOption($option)
    {
        $value = NULL;
        
        ldap_get_option($this->ldap, $option, $value);
            
        return $value;
    }
    
    /**
     * Set LDAP Option
     * @param int $option
     * @param mixed $value
     * @return bool
     */
    public function setLDAPOption($option, $value)
    {
        return ldap_set_option($this->ldap, $option, $value);
    }
    
    /**
     * Set Array of Options
     * @param array $options
     * @return bool
     */
    public function setLDAPOptions(array $options)
    {
        $result = true;
        
        foreach ($options as $option => $value) {
            if (is_string($option)) {
                $option = constant($option);
            }
            
            $result &= $this->setLDAPOption($option, $value);
        }
        
        return $result;
    }
    
    /**
     * Try to bind using Credentials in Config or parameters
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function bind($username=NULL, $password=NULL)
    {        
        $f3 = \Base::instance();
        
        $user = $username?$username:$f3->get('ldap.USERNAME');
        $pass = $password?$password:$f3->get('ldap.PASSWORD');
        
        if (ldap_bind($this->ldap, $user, $pass)) {
            $this->authUser = $user;
        }
        
        return $this;
    }    
    
    /**
     * Try a Bind (useful for Authentication checks
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function tryBind($username, $password)
    {
        return @ldap_bind($this->ldap, $username, $password);                   
    }
    
    
    /**
     * Returns true if a valid connection is established
     * @return bool
     */
    public function isConnected()
    {
        return ($this->ldap && $this->authUser);
    }
    
    /**
     * Get Authenticated User
     * @param bool $getDN
     * @param int $ttl
     * @return string
     */
    public function getAuthUser($getDN=false, $ttl=0)    
    {
        if (!$getDN) {
            return $this->authUser;
        }
        
        if (!$this->authUser) {
            return '';
        }
        
        $filter = "(|";
        foreach ($this->authAttributes as $attr) {
            $filter .= "($attr=$this->authUser)";
        }
        $filter .= ")";
        
        $res = $this->search(NULL, $filter, ['dn'])->getFirstEntry($ttl);
        
        return isset($res['dn'])?$res['dn']:'';        
    }         
    
    /**
     * Set custom Auth Attributes (defaults: mail, cn, uid)
     * @param array $authAttributes
     * @return $this
     */
    public function setAuthAttributes(array $authAttributes)
    {
        $this->authAttributes = $authAttributes;
        
        return $this;
    }
    
    /**
     * Get Auth Attributes
     * @return array
     */
    public function getAuthAttributes()
    {
        return $this->authAttributes;
    }
    
    /**
     * Set Base DN
     * @param string $baseDN
     * @return $this
     */
    public function setBaseDN($baseDN)
    {
        $this->baseDN = $baseDN;
        
        return $this;
    }
    
    /**
     * Get Base DN
     * @return string
     */
    public function getBaseDN()
    {
        return $this->baseDN;
    }
    
    /**
     * Get Error Message/Error Code
     * @param bool $errCode Return Error Code instead of Message
     * @return string|int
     */
    public function getError($errCode=false)
    {
        if ($errCode) {
            return ldap_errno($this->ldap);
        } else {
            return ldap_error($this->ldap);
        }
    }
    
    /**
     * Start a search
     * @param string $searchdn
     * @param string $filter
     * @param array $attributes
     * @param type $scope
     * @param type $attrsonly
     * @param type $sizelimit
     * @param type $timelimit
     * @param type $deref
     * @return $this
     */
    public function search($searchdn=NULL, $filter='(objectclass=*)', array $attributes=array(), $scope=SELF::SCOPE_SUBTREE, $attrsonly=0, $sizelimit=0, $timelimit=0, $deref=LDAP_DEREF_NEVER)
    {               
        $this->searchparams = "$filter.$searchdn.".implode('.',$attributes).".$scope.$attrsonly.$sizelimit";
        
        switch ($scope) {
            case SELF::SCOPE_BASE:
                $this->searchResult = @ldap_read(   $this->ldap,
                                                    $searchdn,
                                                    $filter,
                                                    $attributes,
                                                    $attrsonly,
                                                    $sizelimit,
                                                    $timelimit,
                                                    $deref
                                                ); 
                break;
            
            case SELF::SCOPE_ONELEVEL:
                $this->searchResult = @ldap_list(   $this->ldap, 
                                                    $searchdn?$searchdn:$this->baseDN, 
                                                    $filter, 
                                                    $attributes,
                                                    $attrsonly,
                                                    $sizelimit,
                                                    $timelimit,
                                                    $deref
                                                );                
                break;
            
            case SELF::SCOPE_SUBTREE:
                $this->searchResult = @ldap_search( $this->ldap, 
                                                    $searchdn?$searchdn:$this->baseDN, 
                                                    $filter, 
                                                    $attributes,
                                                    $attrsonly,
                                                    $sizelimit,
                                                    $timelimit,
                                                    $deref
                                                );                 
                break;
        }        
        
        return $this;        
    }
    
    /**
     * Add Entry at given dn
     * @param string $dn
     * @param array $entry
     * @return bool
     */
    public function add($dn, array $entry)
    {
        // Invalidate Cache (structure-change, invalidate all)
        $this->clearCache(); 
        
        return ldap_add($this->ldap, $dn, $entry);
    }
    
    /**
     * Save changes to dn
     * @param string $dn
     * @param array $changes
     * @return bool
     */
    public function save($dn, array $changes)
    {
        // Invalidate Cache (only DN is affected)
        $f3    = \Base::instance();
        $this->clearCache('ldap.dn.'.$f3->hash($dn));         
        
        return ldap_modify($this->ldap, $dn, $changes);
    }
    
    /**
     * Rename a dn 
     * @param string $dn
     * @param string $newrdn
     * @return bool
     */
    public function rename($dn, $newrdn)
    {
        // Invalidate Cache (structure-change, invalidate all)
        $this->clearCache();       
        
        if (($rdns = ldap_explode_dn($dn, 0)) !== false) {
            unset($rdns['count']);
            array_shift($rdns);
            $parentdn = implode(',', $rdns);
        }

        return ldap_rename($this->ldap, $dn, $newrdn, $parentdn, true);
    }
    
    /**
     * Move dn to another parent
     * @param string $dn
     * @param string $newparent
     * @return bool
     */
    public function move($dn, $newparent)
    {               
        // Invalidate Cache (structure-change, invalidate all)        
        $this->clearCache();     
        
        if (($rdns = ldap_explode_dn($dn, 0)) !== false) {
            $rdn = $rdns[0];
        }
        
        return ldap_rename($this->ldap, $dn, $rdn, $newparent, true);
    }
    
    /**
     * Remove DN
     * @param string $dn
     * @return bool
     */
    public function erase($dn)
    {
        // Invalidate Cache (structure-change, invalidate all)        
        $this->clearCache();
        
        return ldap_delete($this->ldap, $dn);
    }

    /**
     * Count results in searchresult
     * @param int $ttl
     * @return int
     */
    public function count($ttl=0)
    {
        $count = 0;
        $cache = \Cache::instance();
        $cacheHash = 'ldap.count.'.$this->getSearchHash();
        
        if ($cache->exists($cacheHash)) {
            return $cache->get($cacheHash);
        } 
        
        if ($this->searchResult instanceof LDAP\Result || is_resource($this->searchResult)) {
            $count = ldap_count_entries($this->ldap, $this->searchResult);
        }
            
        if ($ttl) {
            $cache->set($cacheHash, $count, $ttl);
        }            
        
        return $count;
    }    
    
    /**
     * Retrieve all results
     * @param int $ttl
     * @return array
     */
    public function getAll($ttl=0)
    {
        $cache = \Cache::instance();
        $cacheHash = 'ldap.searchAll.'.$this->getSearchHash();
        
        if ($cache->exists($cacheHash)) {
            return $cache->get($cacheHash);
        }                
        
        $entries = $this->getEntries();
        
        if ($ttl) {
            $cache->set($cacheHash, $entries, $ttl);        
        }            
        
        return $entries;
    }
    
    /**
     * Retrieve first Entry
     * @param int $ttl
     * @return array
     */
    public function getFirstEntry($ttl=0)
    {
        return $this->first()->getEntry($ttl);
    }
    
    /**
     * Retrieve current Entry
     * @param int $ttl
     * @return array
     */
    public function getEntry($ttl=0)
    {
        return $this->getSingleEntry($ttl);
    }
    
    /**
     * Set cursor to first Entry
     * @return $this
     */
    public function first()
    {
        if ($this->searchResult instanceof LDAP\Result || is_resource($this->searchResult)) {
            $this->curEntry = ldap_first_entry($this->ldap, $this->searchResult);        
        }

        return $this;
    }    
    
    /**
     * Move cursor one Entry further
     * @return $this
     */
    public function next()
    {
        if ($this->curEntry) {            
            $this->curEntry = ldap_next_entry($this->ldap, $this->curEntry);        
        }

        return $this;        
    }
    
    /**
     * Get Name of the first Attribute in current Entry
     * @return string
     */
    public function getFirstAttribute()
    {
        if (!$this->curEntry) {
            return false;
        }
        
        return ldap_first_attribute($this->ldap, $this->curEntry);
    }
    
    /**
     * Get Name of next Attribute in current Entry
     * @return string
     */
    public function getNextAttribute()
    {    
        if (!$this->curEntry) {
            return false;
        }        
        
        return ldap_next_attribute($this->ldap, $this->curEntry);        
    }
    
    /**
     * Retrieve all references in current search result
     * @return array
     */
    public function getAllReferences()
    {
        $referrals = [];
        if ($this->searchResult instanceof LDAP\Result || is_resource($this->searchResult)) {            
            ldap_parse_result($this->ldap, $this->searchResult, $errcode, $matcheddn, $errmsg, $referrals);
        }
        
        return $referrals;
    }
    
    /**
     * Free results
     * @return bool
     */
    public function free()
    {
        $result = false;

        if ($this->searchResult instanceof LDAP\Result || is_resource($this->searchResult)) {
            $result = ldap_free_result($this->searchResult);
        }
        $this->curEntry = false;
        $this->searchparams = '';
        
        return $result;
    }
    
    /**
     * Clear cached results
     * @param string $cacheEntry
     * @return bool
     */
    public function clearCache($cacheEntry=false)
    {
        $cache = \Cache::instance();        
        
        if ($cacheEntry) {
            return $cache->clear($cacheEntry);
        } else {
            //clear whole cache
            return $cache->reset();
        }
    }
       
    /**
     * Retrieve Entry at current Cursor
     * @param int $ttl
     * @return array
     */
    private function getSingleEntry($ttl=0)
    {
        if (!$this->curEntry) {
            return [];
        }

        $dn = ldap_get_dn($this->ldap, $this->curEntry);
        
        $f3 = \Base::instance();                
        $cache = \Cache::instance();
        $cacheHash = 'ldap.dn.'.$f3->hash($dn);
        
        if ($cache->exists($cacheHash)) {
            return $cache->get($cacheHash);
        }        
            
        $entry = $this->cleanUpEntry(ldap_get_attributes($this->ldap, $this->curEntry));   
        $entry['dn'] = $dn;
        
        if ($ttl) {
            $cache->set($cacheHash, $entry, $ttl);
        }            
        
        return $entry;           
    }    
    
    /**
     * Retrieve multiple Entries of current search
     * @return array
     */
    private function getEntries()
    {     
        $entries = [];
        
        if ($this->searchResult instanceof LDAP\Result || is_resource($this->searchResult)) {
            $entries = ldap_get_entries($this->ldap, $this->searchResult);
        }
        
        foreach ($entries as $key => &$entry) {
            if (is_array($entry)) {
                $entry = $this->cleanUpEntry($entry);
            } else {
                // unset 'count'
                unset($entries[$key]);
            }
        }
        
        return $entries;        
    }       

    /**
     * Create hash of current Host+Search for caching
     * @return type
     */
    private function getSearchHash()
    {
        $f3 = \Base::instance();
        
        return $f3->hash($this->host.$this->searchparams);
    }
    
    /**
    * Take an LDAP and make an associative array from it.
    *
    * This function takes an LDAP entry in the ldap_get_entries() style and
    * converts it to an associative array like ldap_add() needs.
    *
    * @param array $entry is the entry that should be converted.
    * @return array
    */
    private function cleanUpEntry(array $entry ) 
    {   
        $retEntry = array();
        
        for ($i=0; $i<$entry['count']; $i++) {
            $attribute = $entry[$i];
            
            if ($entry[$attribute]['count'] === 1) {
                $retEntry[$attribute] = $entry[$attribute][0];
            } elseif ($entry[$attribute]['count'] > 1) {
                for ($j=0; $j<$entry[$attribute]['count']; $j++) {
                    $retEntry[$attribute][] = $entry[$attribute][$j];
                }
            } else {
                $retEntry[$attribute] = NULL;
            }
        }
        
        return $retEntry;
    }    
}