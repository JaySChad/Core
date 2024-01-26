<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\DataTypes\JsonSchemaValidationError;
use JsonSchema\Validator;

class JsonDataType extends TextDataType
{

    private $prettify = false;
    
    private $schema = null;

    /**
     * Returns true if the JSON should be formatted in human-readable form, false otherwise.
     * 
     * @return boolean
     */
    public function getPrettify()
    {
        return $this->prettify;
    }

    /**
     * Set to true to export JSON in a human readable form (line-breaks, intendations).
     * 
     * default: false
     * 
     * e.g:
     * false:
     * {"key1":"value1","key2":"value2"}
     * 
     * true:
     * {
     *     "key1": "value1",
     *     "key2": "value2"
     * }
     * 
     * @uxon-property prettify
     * @uxon-type boolean
     * 
     * @param boolean $prettify
     * @return JsonDataType
     */
    public function setPrettify($prettify)
    {
        $this->prettify = BooleanDataType::cast($prettify);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::cast()
     */
    public static function cast($stringOrArrayOrObject)
    {
        if (is_array($stringOrArrayOrObject) || $stringOrArrayOrObject instanceof \stdClass) {
            return $stringOrArrayOrObject;
        }
        
        $stringOrArrayOrObject = trim($stringOrArrayOrObject);
        
        if ($stringOrArrayOrObject === '') {
            return '{}';
        }
        
        if ($stringOrArrayOrObject === null) {
            return null;
        }
        
        return $stringOrArrayOrObject;
    }
    
    public static function isValueEmpty($string) : bool
    {
        return parent::isValueEmpty($string) === true || $string === '{}' || $string === '[]';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::parse()
     */
    public function parse($stringOrArrayOrObject)
    {
        if ($stringOrArrayOrObject === '') {
            return '{}';
        }
        
        if ($stringOrArrayOrObject === null) {
            return null;
        }
        
        try {
            if (is_array($stringOrArrayOrObject) || $stringOrArrayOrObject instanceof \stdClass) {
                $instance = $stringOrArrayOrObject;
            } else {
                $instance = $this::decodeJson($stringOrArrayOrObject, false);
            }
        } catch (DataTypeCastingError $e) {
            throw $this->createValidationError($e->getMessage(), $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw $this->createValidationError('Invalid value "' . $stringOrArrayOrObject . '" for data type ' . $this->getAliasWithNamespace() . '!', null, $e);
        }
        return $this::encodeJson($instance, $this->getPrettify());
    }
    
    /**
     * Decodes a JSON string into a PHP array (default!) or \stdClass object.
     * 
     * WARNING: handling a complex JSON as an array may have side-effects:
     * empty objects `{}` will not be different from empty arrays `[]`, thus
     * transforming a string into an array and back may not work as expected!
     * 
     * @param string $anything
     * @param bool $toArray
     * @throws DataTypeCastingError
     * @return array|\stdClass|mixed
     */
    public static function decodeJson(string $anything, bool $toArray = true)
    {
        $arrayOrObj = json_decode($anything, ($toArray === true ? true : null));
        if ($arrayOrObj === null && $anything !== null) {
            throw new DataTypeCastingError('Cannot parse string "' . substr($anything, 0, 50) . '" as JSON: ' . json_last_error_msg() . ' in JSON decoder!');
        }
        return $arrayOrObj;
    }

    /**
     * 
     * @param mixed $json
     * @param bool $prettify
     * @return string
     */
    public static function encodeJson($anything, bool $prettify = false): string
    {
        $options = null;
        if ($prettify === true) {
            $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;
        }
        $result = json_encode($anything, $options);
        if ($result === false && $anything !== false) {
            throw new DataTypeCastingError('Cannot encode "' . gettype($anything) . '" as JSON: ' . json_last_error_msg() . ' in JSON encoder!');
        }
        return $result;
    }
    
    /**
     *
     * @return string
     */
    public function getSchema() : ?string
    {
        return $this->schema;
    }
    
    /**
     * 
     * @uxon-property schema
     * @uxon-type string
     * 
     * @param string $value
     * @return JsonDataType
     */
    public function setSchema(string $value) : JsonDataType
    {
        $this->schema = $value;
        return $this;
    }
    
    /**
     * 
     * @param array $json
     * @param string $path
     * @throws RuntimeException
     * @return mixed
     */
    public static function filterXPath($json, string $path)
    {
        switch (true) {
            case is_array($json):
                $array = $json;
                break;
            case $json instanceof \stdClass:
                $array = (array) $json;
                break;
            case $json === null:
            case $json === '':
                return $json;
            case is_string($json):
                $array = json_decode($json, true);
                break;
            default:
                throw new InvalidArgumentException('Cannot apply XPath filter to JSON: not a valid JSON!');
        }
        
        return ArrayDataType::filterXPath($array, $path);
    }
    
    /**
     * Returns a pretty printed string for the given JSON, array or stdClass object.
     * 
     * @param string|array|object $json
     * @return string
     */
    public static function prettify($json) : string
    {
        if (is_string($json)) {
            $obj = json_decode($json);
        } else {
            $obj = $json;
        }
        return json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Validate json against a specified json schema. 
     * 
     * @param string|array|\stdClass $stringOrObjectOrArray
     * @param string|array|\stdClass $stringOrObjectOrArray
     * @param string $schema
     * @throws JsonSchemaValidationError
     * @return bool
     */
    public static function validateJsonSchema($json, $schemaJson) : bool
    {
    	$convertIntoStdClass = function ($mixedJson) {    		
    		switch (true){
    			case is_string($mixedJson):
    				return json_decode($mixedJson);
    			case is_object($mixedJson) && $mixedJson instanceof (\stdClass):
    				return $mixedJson;
    			case is_array($mixedJson):
    				return (object)$mixedJson;
    		}
    	};
    	
    	$validator = (new Validator());
    	$validator->validate($convertIntoStdClass($json), $convertIntoStdClass($schemaJson));
    	
    	
        if (count($validator->getErrors()) !== 0) {
        	throw new JsonSchemaValidationError(
        		$validator->getErrors(), 
        		'Json does not match given schema',
        		json: $json);
        }
        
        return $validator->isValid();
    }
}