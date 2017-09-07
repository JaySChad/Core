<?php
namespace exface\Core\CommonLogic;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\UxonMapError;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\Exceptions\LogicException;

class UxonObject implements \IteratorAggregate
{
    private $array = [];
    
    public function __construct(array $properties = [])
    {
        $this->array = $properties;
    }

    /**
     * Returns true if there are not properties in the UXON object
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return empty($this->array) ? true : false;
    }
    
    public function isPropertyEmpty($property_name)
    {
        return empty($this->array[$property_name]) ? true : false;
    }

    /**
     * Returns the actual UXON code (in JSON notation).
     * The output can be optionally prettified, improving human readability
     *
     * @param boolean $prettify            
     */
    public function toJson($prettify = false)
    {
        $options = $prettify ? JSON_PRETTY_PRINT : null;
        return json_encode($this->toArray(), $options);
    }

    /**
     * Creates a UXON object from a JSON string
     *
     * @param string $uxon            
     * @return UxonObject
     */
    public static function fromJson($uxon)
    {
        $array = json_decode($uxon, true);
        if (is_array($array)){
            return static::fromArray($array);
        } else {
            return new self();
        }
    }

    /**
     * Creates a UXON object from an array.
     * The resulting UXON will be an array itself, but alle elements will get transformed
     * to UXON objects.
     *
     * @param array $array            
     * @return UxonObject
     */
    public static function fromArray(array $array)
    {
        return new self($array);
    }

    /**
     * Attempts to create a UxonObject autodetecting the type of input
     *
     * @param mixed $string_or_array_or_object            
     * @return UxonObject
     */
    public static function fromAnything($string_or_array_or_object)
    {
        if ($string_or_array_or_object instanceof UxonObject) {
            return $string_or_array_or_object->copy();
        } elseif (is_array($string_or_array_or_object)) {
            return self::fromArray($string_or_array_or_object);
        } else {
            return self::fromJson($string_or_array_or_object);
        }
    }

    /**
     * Returns a property specified by name (alternative to $uxon->name)
     *
     * @param string $name            
     * @return mixed
     */
    public function getProperty($name)
    {
        $val = $this->array[$name];
        return is_array($val) ? new self($val) : $val;
    }

    /**
     *
     * @param string $name            
     * @return boolean
     */
    public function hasProperty($name)
    {
        return array_key_exists($name, $this->array);
    }

    /**
     *
     * @param string $name            
     * @param boolean $case_sensitive            
     * @return mixed
     */
    public function findPropertyKey($name, $case_sensitive = false)
    {
        if ($this->hasProperty($name)) {
            return $name;
        } else {
            $property_names = array_keys($this->array);
            foreach ($property_names as $property_name) {
                if (strcasecmp($name, $property_name) === 0){
                    return $property_name;
                }
            }
            return false;
        }
    }

    /**
     * Returns all properties of this UXON object as an assotiative array
     *
     * @return array
     */
    public function getPropertiesAll()
    {
        $array = [];
        foreach ($this->array as $var => $val){
            $array[$var] = $this->getProperty($var);
        }
        return $array;
    }

    /**
     * Adds a property to the UXON object.
     * Property values may be scalars, arrays, stdClasses or other UxonObjects
     *
     * @param string $property_name            
     * @param UxonObject|string $scalar_or_uxon   
     * @return \exface\Core\CommonLogic\UxonObject         
     */
    public function setProperty($property_name, $scalar_or_uxon)
    {
        $this->array[$property_name] = $this->sanitizePropertyValue($scalar_or_uxon);
        return $this;
    }
    
    protected function sanitizePropertyValue($scalar_or_uxon)
    {
        return $scalar_or_uxon instanceof UxonObject ? $scalar_or_uxon->toArray() : $scalar_or_uxon;
    }
    
    /**
     * 
     * @param string $property_name
     * @param UxonObject|string $scalar_or_uxon
     * @throws UxonParserError
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function appendToProperty($property_name, $scalar_or_uxon)
    {
        if (! array_key_exists($property_name, $this->array)){
            $this->array[$property_name] = [];
        } elseif (is_scalar($this->array[$property_name])){
            throw new UxonParserError($this, 'Cannot append "' . $scalar_or_uxon . '" to UXON property "' . $property_name . '": the property is a of a scalar type!');
        }
        $this->array[$property_name][] = $this->sanitizePropertyValue($scalar_or_uxon);
        return $this;
    }
    
    public function append($scalar_or_uxon)
    {
        $this->array[] = $this->sanitizePropertyValue($scalar_or_uxon);
        return $this;
    }
    
    public function countProperties()
    {
        return count($this->array);
    }

    /**
     * Extends this UXON object with properties of the given one.
     * Conflicting properties will be overridden with
     * values from the argument object!
     *
     * @param UxonObject $extend_by_uxon            
     * @return UxonObject
     */
    public function extend(UxonObject $extend_by_uxon)
    {
        // before new UxonObject: return self::fromStdClass((object) array_merge((array) $this, (array) $extend_by_uxon));
        return new self(array_replace_recursive($this->array, $extend_by_uxon->toArray()));
    }

    /**
     * Returns a full copy of the UXON object.
     * This is a deep copy including arrays, etc. in contrast to the built-in
     * PHP clone.
     *
     * @return UxonObject
     */
    public function copy()
    {
        return new self($this->array);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->getPropertiesAll());
    }

    /**
     * Removes the given property from the UXON object
     *
     * @param string $name            
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function unsetProperty($name)
    {
        unset($this->array[$name]);
        return $this;
    }

    public function toArray()
    {
        return $this->array;
    }

    /**
     * Finds public setter methods in the given class mathing properties of this UXON object and calls them for each property.
     *
     * NOTE: this only works with public setters as private and protected methods cannot be called from the UXON object. To
     * work with non-public setters use the ImportUxonObjectTrait in your enitity!
     *
     * @param object $target_class_instance            
     * @throws UxonMapError
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function mapToClassSetters($target_class_instance)
    {
        if (! is_object($target_class_instance)) {
            throw new UxonMapError($this, 'Cannot import UXON configuration to "' . gettype($target_class_instance) . '": only instantiated PHP classes supported!');
        }
        
        foreach ($this->array as $var => $val) {
            $setterCamelCased = 'set' . StringDataType::convertCaseUnderscoreToPascal($var);
            if (method_exists($target_class_instance, $setterCamelCased)) {
                call_user_func(array(
                    $target_class_instance,
                    $setterCamelCased
                ), $val);
            } elseif (method_exists($target_class_instance, 'set_' . $var)) {
                call_user_func(array(
                    $target_class_instance,
                    'set_' . $var
                ), $val);
            } else {
                throw new UxonMapError($this, 'No setter method found for UXON property "' . $var . '" in "' . get_class($target_class_instance) . '"!');
            }
        }
        return $this;
    }
    
    public function isArray($ignore_gaps_in_keys = false)
    {
        if ($this->isEmpty()){
            return true;
        }
        
        foreach (array_keys($this->array) as $key){
            if (!is_numeric($key)){
                return false;
            }
        }
        
        if ($ignore_gaps_in_keys == false){
            return array_keys($this->array) === range(0, count($this->array) - 1);
        }
        
        return true;
    }
    
    public function __get($name)
    {
        throw new LogicException('Direct access to properties of a UxonObject is not supported anymore!');
    }
    
    public function __set($name, $value)
    {
        throw new LogicException('Direct access to properties of a UxonObject is not supported anymore!');
    }
    
    
}