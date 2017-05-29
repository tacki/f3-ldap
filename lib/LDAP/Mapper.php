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
namespace LDAP;

use LDAP;

class Mapper extends \DB\Cursor
{
    /**     
     * @var resource
     */
    protected $ldap;
    
    /**
     * @var string
     */
    protected $baseDn;    
    
    /**     
     * @var string
     */
    protected $dn;
    
    /**    
     * @var array
     */
    protected $data = [];
    
    /**     
     * @var array
     */
    protected $origData = [];
    
    /**
     * Constructor
     * @param LDAP $ldap
     */
    public function __construct(LDAP $ldap, string $baseDn=NULL)
    {
        $this->ldap = $ldap;
        $this->baseDn = $baseDn;
    }
    
    /**
     * Return DB Type
     * @return string
     */
    public function dbtype()
    {
        return 'LDAP';
    }    
    
    /**
     * Return the fields of the mapper object as an associative array
     * @param LDAP $obj
     * @return array
     */
    function cast($obj = NULL)
    {
        if (!$obj) {
            $obj=$this;
        }
        return $obj->data+['dn'=>$this->dn];
    }
    
    /**
     *	Insert new record
     *	@return bool
     */
    public function insert() 
    {          
        return $this->ldap->add($this->dn, $this->data);
    }         
    
    /**
     *	Update current record
     *	@return bool
     */
    public function update() 
    {    
        $changes = $this->getChanges();
        $this->clearChanges();
        return $this->ldap->save($this->dn, $changes);
    }  
    
    /**
     *	Convert array to mapper object
     *	@param string $dn
     *	@param array $data
     *	@return object 
     */
    protected function factory(array $data) 
    {
            $mapper=clone($this);
            $mapper->reset();
            $mapper->dn=$data['dn'];
            foreach ($data as $field=>$val) {
                    $mapper->data[$field]=$val;
            }
            $mapper->query=[clone($mapper)];
            if (isset($mapper->trigger['load'])) {
                    \Base::instance()->call($mapper->trigger['load'],$mapper);
            }
            return $mapper;
    }    
        
    /**
     * Start a Search
     * @param string $filter
     * @param array $options
     * @param int $ttl
     * @return array
     */
    public function find($filter=NULL, array $options=NULL, $ttl=0)
    {
        $out=[];
        
        $options = $this->filterOptions($options);
        
        $search = $this->ldap->search(  
                                        $this->baseDn, 
                                        $filter?$filter:'(objectClass=*)', 
                                        $options['attributes'], 
                                        $options['scope'], 
                                        $options['attronly'], 
                                        $options['sizelimit'], 
                                        $options['timelimit'], 
                                        $options['deref']
                                    );
        
        if ($options['limit'] === 1) {
            $entries = array($search->getFirstEntry($ttl));
        } else {
            $entries = $search->getAll($ttl);
        }
        
        foreach ($entries as &$entry) {
            $out[] = $this->factory($entry);
            unset($entry);
        }                
        
        return $out;
    }
        
    /**
     * Count results of a given search
     * @param string $filter
     * @param array $options
     * @param int $ttl
     * @return int
     */
    public function count($filter=NULL, array $options=NULL, $ttl=0)
    {
        $options = $this->filterOptions($options);
        
        return $this->ldap->search( 
                                    $this->baseDn, 
                                    $filter?$filter:'(objectClass=*)',
                                    $options['attributes'], 
                                    $options['scope'], 
                                    $options['attronly'], 
                                    $options['sizelimit'], 
                                    $options['timelimit'], 
                                    $options['deref']
                                )->count($ttl);
    }
    
    /**
     * Erase mapped entry/entries
     * @param string $filter
     * @return bool
     */
    public function erase(string $filter=NULL)
    {  
        if ($filter) {
            foreach ($this->find($filter) as $mapper) {
                $out+=$mapper->erase();
            }
            return $out;            
        }
        $out = $this->ldap->erase($this->dn);
        parent::erase();
        
        return $out;
    }   
    
    /**
     * Get Attribute names
     * @return array
     */
    public function fields()
    {
        return array_keys($this->data);
    }
    
    /**
     *	Retrieve external iterator for fields
     *	@return \ArrayIterator
    **/
    function getIterator() 
    {        
        return new \ArrayIterator($this->cast());
    }    
    
    /**
     * Hydrate mapper object using hive array variable
     * @param array|string $var
     * @param callback $func
     */
    public function copyfrom($var,$func=NULL) 
    {
        if (is_string($var)) {
            $var=\Base::instance()->$var;
        }
        if ($func) {
            $var=call_user_func($func,$var);
        }
        foreach ($var as $key=>$val) {
            if (in_array($key,array_keys($this->data))) {
                $this->set($key,$val);
            }
        }
    }   
    
    /**
     *	Populate hive array variable with mapper fields
     *	@param string $key
     */
    public function copyto($key) 
    {
        $var=&\Base::instance()->ref($key);
        foreach ($this->data as $key=>$field) {
            $var[$key]=$field;
        }
    }   
    
    /**
     * Get changed Data in this object
     * @return array
     */
    public function getChanges()
    {
        return $this->arrayRecursiveDiff($this->data, $this->origData);
    }
    
    /**
     * Reset changed Data info
     */
    public function clearChanges()
    {
        $this->origData = $this->data;
    }
    
    /**
     * Check if given Attribute exists
     * @param string $attribute
     * @return bool
     */
    public function exists($attribute) 
    {
        return array_key_exists($attribute,$this->data);
    }

    /**
     * Set value
     * @param string $attribute
     * @param mixed $val
     */
    public function set($attribute, $val) 
    {
        if ($attribute == 'dn') {
            $this->dn = $val; 
        } else {
            $this->data[$attribute]=$val;
        }
    }

    /**
     * Get Value
     * @param string $attribute
     * @return mixed
     */
    public function &get($attribute) 
    {       
        if ($attribute == 'dn') {
            return $this->dn;
        }
        if (array_key_exists($attribute,$this->data)) {
            return $this->data[$attribute];
        }
        user_error(sprintf(self::E_Field,$attribute),E_USER_ERROR);    
    }

    /**
     * Clear given attribute
     * @param string $attribute
     */
    public function clear($attribute) 
    {
        if ($attribute!='dn') {
            unset($this->data[$attribute]);
        }
    }  
    
    /**
     *	Return record at specified offset using criteria of previous
     *	load() call and make it active
     *	@param int $ofs
     *	@return array      
     */
    public function skip($ofs=1) 
    {
            $this->data=($out=parent::skip($ofs))?$out->data:[];
            $this->origData = $this->data;
            $this->dn=$out?$out->dn:NULL;
            if ($this->data && isset($this->trigger['load'])) {
                    \Base::instance()->call($this->trigger['load'],$this);
            }
            return $out;
    }    
    
    /**
     *	Reset cursor
     */
    public function reset() 
    {
        $this->dn=NULL;
        $this->data=[];
        parent::reset();
    }    
    
    /**
     * Filter Options
     * @param array $options
     * @return type
     */
    private function filterOptions($options)
    {
        $result = [];
        $result['attributes']   = (array)$options['attributes']?$options['attributes']:[];
        $result['scope']        = (int)$options['scope']?$options['scope']:LDAP::SCOPE_SUBTREE;
        $result['attronly']     = (int)$options['attronly']?$options['attronly']:0;
        $result['limit']        = (int)$options['limit']?$options['limit']:0;
        $result['timelimit']    = (int)$options['timelimit']?$options['timelimit']:0;
        $result['deref']        = (int)$options['deref']?$options['deref']:LDAP_DEREF_NEVER;         
        return $result;
    }
    
    /**
     * Compare two arrays and get diff (recursive)
     * @param array $array1
     * @param array $array2
     * @return array
     */
    private function arrayRecursiveDiff(array $array1, array $array2) {
        $result = array();

        foreach ($array1 as $key => $value) {
            if (array_key_exists($key, $array2)) {
                if (is_array($value) && is_array($array2[$key])) {
                    $diff = $this->arrayRecursiveDiff($value, $array2[$key]);
                    if (count($diff)) { 
                        $result[$key] = $diff;                       
                    }
                } else {
                    if ($value !== $array2[$key]) {
                        $result[$key] = $value;
                    }
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}

