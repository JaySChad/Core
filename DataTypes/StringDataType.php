<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;

/**
 * Basic data type for textual values.
 * 
 * Strings can contain any characters, but can be restricted in length and
 * validating using regular expressions.
 * 
 * @author Andrej Kabachnik
 *
 */
class StringDataType extends AbstractDataType
{
    private $lengthMin = 0;
    
    private $lengthMax = null;
    
    private $regexValidator = null;

    /**
     * @return string|null
     */
    public function getValidatorRegex()
    {
        return $this->regexValidator;
    }

    /**
     * Defines a regular expression to validate values of this data type.
     * 
     * Use regular expressions compatible with PHP preg_match(). A good
     * tool to create and test regular expressions can be found here:
     * https://regex101.com/.
     * 
     * @uxon-property validator_regex
     * @uxon-type string
     * 
     * @param string $regularExpression
     * @return StringDataType
     */
    public function setValidatorRegex($regularExpression)
    {
        $this->regexValidator = $regularExpression;
        return $this;
    }

    /**
     * Converts a string from under_score (snake_case) to camelCase.
     *
     * @param string $string            
     * @return string
     */
    public static function convertCaseUnderscoreToCamel($string)
    {
        return lcfirst(static::convertCaseUnderscoreToPascal($string));
    }

    /**
     * Converts a string from camelCase to under_score (snake_case).
     *
     * @param string $string            
     * @return string
     */
    public static function convertCaseCamelToUnderscore($string)
    {
        return static::convertCasePascalToUnderscore($string);
    }

    /**
     * Converts a string from under_score (snake_case) to PascalCase.
     *
     * @param string $string            
     * @return string
     */
    public static function convertCaseUnderscoreToPascal($string)
    {
        return str_replace('_', '', ucwords($string, "_"));
    }

    /**
     * Converts a string from PascalCase to under_score (snake_case).
     *
     * @param string $string            
     * @return string
     */
    public static function convertCasePascalToUnderscore($string)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    /**
     *
     * @param string $haystack            
     * @param string $needle            
     * @param boolean $case_sensitive            
     * @return boolean
     */
    public static function startsWith($haystack, $needle, $case_sensitive = true)
    {
        if ($case_sensitive) {
            return substr($haystack, 0, strlen($needle)) === $needle;
        } else {
            return substr(mb_strtoupper($haystack), 0, strlen(mb_strtoupper($needle))) === mb_strtoupper($needle);
        }
    }
    
    /**
     *
     * @param string $haystack
     * @param string $needle
     * @param boolean $case_sensitive
     * @return boolean
     */
    public static function endsWith($haystack, $needle, $case_sensitive = true)
    {
        if ($case_sensitive) {
            return substr($haystack, (-1)*strlen($needle)) === $needle;
        } else {
            return substr(mb_strtoupper($haystack), (-1)*strlen(mb_strtoupper($needle))) === mb_strtoupper($needle);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::cast()
     */
    public static function cast($string)
    {
        if (is_scalar($string)){
            $result = $string;
        } elseif (is_array($string)){
            $result = implode(EXF_LIST_SEPARATOR, $string);
        } else {
            $result =  '';
        }
        
        return $result;
    }
    
    public function parse($string){
        $value = parent::parse($string);
        
        // validate length
        $length = mb_strlen($value);
        if ($this->getLengtMin() > 0 && $length < $this->getLengtMin()){
            throw new DataTypeCastingError('The lenght of the string "' . $value . '" (' . $length . ') is less, than the minimum length required for data type ' . $this->getAliasWithNamespace() . ' (' . $this->getLengtMin() . ')!');
        }
        if ($this->getLengthMax() && $length > $this->getLengthMax()){
            $value = substr($value, 0, $this->getLengthMax());
        }
        
        // validate against regex
        if ($this->getValidatorRegex()){
            try {
                $match = preg_match("'" . $this->getValidatorRegex() . "'", $value);
            } catch (\Throwable $e) {
                $match = 0;
            }
            
            if (! $match){
                throw new DataTypeCastingError('Value "' . $value . '" does not match the regular expression mask "' . $this->getValidatorRegex() . '" of data type ' . $this->getAliasWithNamespace() . '!');
            }
        }
        
        return $value;        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirectionsDataType::ASC($this->getWorkbench());
    }
    /**
     * @return integer
     */
    public function getLengtMin()
    {
        return $this->lengthin;
    }

    /**
     * Minimum legnth of the string in characters - defaults to 0.
     * 
     * @uxon-property length_min
     * @uxon-type integer
     * 
     * @param integer $number
     * @return StringDataType
     */
    public function setLengthMin($number)
    {
        $this->lengthin = $number;
        return $this;
    }

    /**
     * @return integer
     */
    public function getLengthMax()
    {
        return $this->lengthMax;
    }

    /**
     * Maximum legnth of the string in characters.
     * 
     * @uxon-property length_max
     * @uxon-type integer
     * 
     * @param integer $number
     * @return StringDataType
     */
    public function setLengthMax($number)
    {
        $this->lengthMax = $number;
        return $this;
    }
    
    /**
     * Returns an array of ExFace-placeholders found in a string.
     * E.g. will return ["name", "id"] for string "Object [#name#] has the id [#id#]"
     *
     * @param string $string
     * @return array
     */
    public static function findPlaceholders($string)
    {
        $placeholders = array();
        preg_match_all("/\[#([^\]\[#]+)#\]/", $string, $placeholders);
        return is_array($placeholders[1]) ? $placeholders[1] : array();
    }
    
    /**
     * Returns the part of the given string ($haystack) preceeding the first occurrence of $needle.
     * 
     * Using the optional parameters you can make the search case sensitive and
     * search for the last occurrence instead of the first one.
     * 
     * Returns $default if the $needle was not found.
     * 
     * @param string $haystack
     * @param string $needle
     * @param mixed $default
     * @param bool $caseSensitive
     * @param bool $useLastOccurance
     * @return string|boolean
     */
    public static function substringBefore(string $haystack, string $needle, $default = false, bool $caseSensitive = false, bool $useLastOccurance = false)
    {
        $substr = '';
        if ($caseSensitive === true) {
            if ($useLastOccurance === true) {
                $pos = strrpos($haystack, $needle);
                if ($pos === false) {
                    $substr = $default;
                } else {
                    $substr = substr($haystack, 0, $pos);
                }
            } else {
                $substr = strstr($haystack, $needle, true);
                if ($substr === false) {
                    $substr = $default;
                }
            }
        } else {
            if ($useLastOccurance) {
                $pos = strripos($haystack, $needle);
                if ($pos === false) {
                    $substr = $default;
                } else {
                    $substr = substr($haystack, 0, $pos);
                }
            } else {
                $substr = stristr($haystack, $needle, true);
                if ($substr === false) {
                    $substr = $default;
                }
            }
        }
        return $substr;
    }
    
    /**
     * Returns the part of the given string ($haystack) following the first occurrence of $needle.
     * 
     * Using the optional parameters you can make the search case sensitive and
     * search for the last occurrence instead of the first one.
     * 
     * @param string $haystack
     * @param string $needle
     * @param mixed $default
     * @param bool $caseSensitive
     * @param bool $useLastOccurance
     * @return string|boolean
     */
    public static function substringAfter(string $haystack, string $needle, $default = false, bool $caseSensitive = false, bool $useLastOccurance = false)
    {
        $substr = '';
        if ($caseSensitive === true) {
            if ($useLastOccurance === true) {
                $pos = strrpos($haystack, $needle);
                if ($pos === false) {
                    $substr = $default;
                } else {
                    $substr = substr($haystack, ($pos+1));
                }
            } else {
                $substr = strstr($haystack, $needle);
                if ($substr === false) {
                    $substr = $default;
                }
            }
        } else {
            if ($useLastOccurance) {
                $pos = strripos($haystack, $needle);
                if ($pos === false) {
                    $substr = $default;
                } else {
                    $substr = substr($haystack, ($pos+1));
                }
            } else {
                $substr = stristr($haystack, $needle);
                if ($substr === false) {
                    $substr = $default;
                }
            }
        }
        return $substr;
    }
}
?>