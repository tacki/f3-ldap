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
     * @var resource 
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
     * @var resource  
     */
    protected $searchResult;
    
    /**
     * @var resource  
     */
    protected $curEntry;    
    
    /**
     * Constructor
     * @param string $ldaphoststring
     * @param string %basedn
     * @param string $username
     * @param string $password
     * @param array $options
     */
    public function __construct(string $ldaphoststring=NULL, string $basedn=NULL, string $username=NULL, string $password=NULL, array $options=array())
    {
        $f3 = \Base::instance();        
        
        if ($ldaphoststring) {                    
            $this->setLDAPOptions($options);            
            $this->connect($ldaphoststring);
            $this->bind($username, $password);
            #
            $this->baseDN = $basedn;
            $this->host = $ldaphoststring;
        } elseif($f3->get('ldap.HOST'))  {                 
            $this->setLDAPOptions((array)$f3->get('ldap.options'));
            $this->connect($f3->get('ldap.HOST'));
            $this->bind($f3->get('ldap.USERNAME'), $f3->get('ldap.PASSWORD'));
            #
            $this->baseDN = $f3->get('ldap.BASEDN');
            $this->host = $f3->get('ldap.HOST');
        }
    }
    
    /**
     * Set LDAP Configuration and connect
     * @param string $ldaphoststring
     * @return $this
     */
    public function connect(string $ldaphoststring=NULL)
    {
        $this->ldap   = ldap_connect($ldaphoststring);  
       
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
    public function getLDAPOption(int $option)
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
    public function setLDAPOption(int $option, $value)
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
    public function bind(string $username=NULL, string $password=NULL)
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
    public function tryBind(string $username, string $password)
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
     * Set Base DN
     * @param string $baseDN
     * @return $this
     */
    public function setBaseDN(string $baseDN)
    {
        $this->baseDN = $baseDN;
        
        return $this;
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
    public function search(string $searchdn=NULL, string $filter='(objectclass=*)', array $attributes=array(), $scope=SELF::SCOPE_SUBTREE, $attrsonly=0, $sizelimit=0, $timelimit=0, $deref=LDAP_DEREF_NEVER)
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
    public function add(string $dn, array $entry)
    {
        return ldap_add($this->ldap, $dn, $entry);
    }
    
    /**
     * Save changes to dn
     * @param string $dn
     * @param array $changes
     * @return bool
     */
    public function save(string $dn, array $changes)
    {
        // Invalidate Cache        
        $f3    = \Base::instance();
        $cache = \Cache::instance();
        $cacheHash = 'ldap.dn.'.$f3->hash($dn);        
        $cache->clear($cacheHash);          
        
        return ldap_modify($this->ldap, $dn, $changes);
    }
    
    /**
     * Rename a dn 
     * @param string $dn
     * @param string $newrdn
     * @return bool
     */
    public function rename(string $dn, string $newrdn)
    {
        // Invalidate Cache        
        $f3    = \Base::instance();
        $cache = \Cache::instance();
        $cacheHash = 'ldap.dn.'.$f3->hash($dn);        
        $cache->clear($cacheHash);        
        
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
    public function move(string $dn, string $newparent)
    {               
        // Invalidate Cache
        $f3    = \Base::instance();
        $cache = \Cache::instance();
        $cacheHash = 'ldap.dn.'.$f3->hash($dn);        
        $cache->clear($cacheHash);        
        
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
    public function erase(string $dn)
    {
        // Invalidate Cache        
        $f3    = \Base::instance();
        $cache = \Cache::instance();
        $cacheHash = 'ldap.dn.'.$f3->hash($dn);        
        $cache->clear($cacheHash);
        
        return ldap_delete($this->ldap, $dn);
    }

    /**
     * Count results in searchresult
     * @param int $ttl
     * @return int
     */
    public function count(int $ttl=0)
    {
        $cache = \Cache::instance();
        $cacheHash = 'ldap.count.'.$this->getSearchHash();
        
        if ($cache->exists($cacheHash)) {
            return $cache->get($cacheHash);
        } 
        
        $count = ldap_count_entries($this->ldap, $this->searchResult);
            
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
    public function getAll(int $ttl=0)
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
    public function getFirstEntry(int $ttl=0)
    {
        return $this->first()->getEntry($ttl);
    }
    
    /**
     * Retrieve current Entry
     * @param int $ttl
     * @return array
     */
    public function getEntry(int $ttl=0)
    {
        return $this->getSingleEntry($ttl);
    }
    
    /**
     * Set cursor to first Entry
     * @return $this
     */
    public function first()
    {
        $this->curEntry = ldap_first_entry($this->ldap, $this->searchResult);        

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
        
        ldap_parse_result($this->ldap, $this->searchResult, $errcode, $matcheddn, $errmsg, $referrals);
        
        return $referrals;
    }
    
    /**
     * Free results
     * @return bool
     */
    public function free()
    {
        $result = ldap_free_result($this->searchResult);
        $this->curEntry = false;
        $this->searchparams = '';
        
        return $result;
    }
       
    /**
     * Retrieve Entry at current Cursor
     * @param int $ttl
     * @return array
     */
    private function getSingleEntry(int $ttl=0)
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
        $entries = ldap_get_entries($this->ldap, $this->searchResult);
        
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
            
            $retEntry['dn'] = $entry['dn'];
        }
        
        return $retEntry;
    }    
}