<?php
/*
 * Copyright (C) 2017 Markus Schlegel <markus.schlegel@roto-frank.com>
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
namespace Library;

class LDAP extends \Prefab
{
    /**   
     * @var resource 
     */
    protected $ldap;

    /**
     * @var array
     */
    protected $config;
    
    /**
     * @var string
     */
    protected $authUser;
        
    /**
     * @var resource  
     */
    protected $search;    
    
    /**
     * @var resource  
     */
    protected $searchResult;
    
    /**
     * Set LDAP Configuration and connect
     * @param array $options
     * @throws \Exception
     */
    public function connect(array $options)
    {
        $this->ldap   = ldap_connect($options['HOST'], $options['PORT']);
        $this->config = $options;
                
        if (!$this->ldap) {
            throw new \Exception('Connection to LDAP-Server failed!');
        }     
        
        ldap_set_option($this->ldap, LDAP_OPT_PROTOCOL_VERSION, 3) ;        
        ldap_set_option($this->ldap, LDAP_OPT_REFERRALS, 0) ;
        
        return $this;
    }
    
    /**
     * Try to bind using Credentials in Config or parameters
     * @param string $username
     * @param string $password
     * @return boolean|$this
     */
    public function bind(string $username="", string $password="")
    {        
        $ldapBind = ldap_bind($this->ldap, 
                              $username?$username:$this->config['USERNAME'], 
                              $password?$password:$this->config['PASSWORD'] );
        
        if (!$ldapBind) {
            throw new \Exception('Invalid Username or Password!');
        }    
        
        $this->authUser = $username?$username:$this->config['USERNAME'];
        
        return $this;
    }    
    
    /**
     * Start Search
     * @param type $search
     * @return $this
     */
    public function search($search, array $attributes=array())
    {        
        if ($search) {           
            $this->search = $search;
            $this->searchResult = ldap_search(  $this->ldap, 
                                                $this->config['BASE'], 
                                                $search, 
                                                $attributes
                                            );           
        }        
        
        return $this;        
    }
    
    /**
     * 
     * @param string $dn
     * @param array $entry
     * @return bool
     */
    public function add(string $dn, array $entry)
    {
        return ldap_add($this->ldap, $dn, $entry);
    }
    
    /**
     * 
     * @param string $dn
     * @param array $changes
     * @return bool
     */
    public function save(string $dn, array $changes)
    {
        return ldap_modify($this->ldap, $dn, $changes);
    }
    
    /**
     * 
     * @param string $dn
     * @return bool
     */
    public function erase(string $dn)
    {
        return ldap_delete($this->ldap, $dn);
    }
    
    /**
     * 
     * @return array
     * @throws \Exception
     */
    public function getFirst()
    {            
        return $this->getOne()[0];
    }
    
    /**
     * 
     * @return int
     * @throws \Exception
     */
    public function count($ttl=0)
    {
        if (!$this->searchResult) {        
            throw new \Exception("Invalid Search Result");
        }
        
        $cache = \Cache::instance();
        $cacheHash = 'count.'.$this->getSearchHash();
        
        if ($cache->exists($cacheHash)) {
            return $cache->get($cacheHash);
        } 
        
        $count = ldap_count_entries($this->ldap, $this->searchResult);
            
        $cache->set($cacheHash, $count, $ttl);
        
        return $count;
    }    
    
    /**
     * 
     * @return array
     * @throws \Exception
     */
    public function getAll($ttl=0)
    {
        if (!$this->searchResult) {        
            throw new \Exception("Invalid Search Result");
        }
        
        ldap_set_option($this->ldap, LDAP_OPT_SIZELIMIT, 100);

        return $this->getEntries($ttl);
    }
    
    /**
     * 
     * @return array
     * @throws \Exception
     */
    public function getOne($ttl=0)
    {
        if (!$this->searchResult) {        
            throw new \Exception("Invalid Search Result");
        }

        ldap_set_option($this->ldap, LDAP_OPT_SIZELIMIT, 1);
        
        return $this->getEntries($ttl);
    }    
    
    private function getEntries(int $ttl)
    {     
        $cache = \Cache::instance();
        $cacheHash = 'search.'.$this->getSearchHash();
        
        if ($cache->exists($cacheHash)) {
            return $cache->get($cacheHash);
        }
        
        $entries = ldap_get_entries($this->ldap, $this->searchResult);
        
        foreach ($entries as $key => &$entry) {
            if (is_array($entry)) {
                $entry = $this->cleanUpEntry($entry);
            } else {
                // unset 'count'
                unset($entries[$key]);
            }
        }
        
        $cache->set($cacheHash, $entries, $ttl);
        
        return $entries;        
    }

    private function getSearchHash()
    {
        $f3 = \Base::instance();
        
        return $f3->hash(implode(".",$this->config).$this->search);
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
        
        for ( $i = 0; $i < $entry['count']; $i++ ) {
            $attribute = $entry[$i];
            
            if ( $entry[$attribute]['count'] == 1 ) {
                $retEntry[$attribute] = $entry[$attribute][0];
            } else {
                for ( $j = 0; $j < $entry[$attribute]['count']; $j++ ) {
                    $retEntry[$attribute][] = $entry[$attribute][$j];
                }
            }
            
            $retEntry['dn'] = $entry['dn'];
        }
        
        return $retEntry;
    }    
}