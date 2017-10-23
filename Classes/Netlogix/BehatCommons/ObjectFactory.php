<?php
namespace Netlogix\BehatCommons;

/*
 * This file is part of the Netlogix.BehatCommons package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Property\PropertyMappingConfiguration;
use Neos\Flow\Property\TypeConverter\PersistentObjectConverter;
use Neos\Utility\ObjectAccess;

/**
 * The input structure of the ObjectFactory is a 2x2 dimension table where a
 * "dot notation property path" in the first column corresponds to a value
 * in the second column.
 *
 * Example:
 *   | property           | value  |
 *   | some.property.path | value2 |
 *
 * Certain value processors are available. Value processors are attached in
 * the format of "-> doSomething()" to the first column.
 *
 * Example:
 *   | property1 -> json_encode()      | ["bar"]           |
 *   | property2 -> htmlspecialchars() | <span>test</span> |
 *   | property3 -> convert(boolean)   | 1                 |
 *
 * When being used within a testing context, every created object is
 * remembered within an internal which allows for object retrieval and
 * manipulation through other testing contexts.
 *
 * @Flow\Scope("singleton")
 */
class ObjectFactory
{
	const METHOD_PATTERN = '%\\s*-\\>\\s*(?<method>[a-z0-9_-]+)\\(\\s*(?<argument>[a-z0-9_-]*)\\s*\\)\\s*$%i';

	/**
	 * @var array<mixed>
	 */
	protected $createdObjects = [];

	/**
	 * @var array<mixed>
	 */
	protected $rememberedObjects = [];

	protected $processors = [];

	/**
	 * @var bool
	 */
	protected $startObjectTracking = false;

	public function beforeScenario()
	{
		$this->startObjectTracking = true;
		$this->createdObjects = [];
		$this->rememberedObjects = [];
	}

	public function afterScenario()
	{
		$this->startObjectTracking = false;
		unset($this->createdObjects);
		unset($this->rememberedObjects);
	}

	/**
	 * Creates a new object based in input parameters and a class name.
	 *
	 * This is basically a wrapper for the PropertyMapper::convert() method that
	 * does not take a hierarchical source array but only a key=>value map of
	 * keys being dot notation property paths.
	 *
	 * @param array $parameters
	 * @param string $className
	 * @param PropertyMapper $propertyMapper
	 * @return mixed
	 */
	public function create(array $parameters, $className, PropertyMapper $propertyMapper)
	{
		list ($objectStructure, $propertyPaths) = $this->getNestedStructureForDottedNotation($parameters);
		$propertyMappingConfiguration = $this->getPropertyMappingConfigurationForPropertyPaths($propertyPaths, $className);

		$result = $propertyMapper->convert($objectStructure, $className, $propertyMappingConfiguration);
		if ($this->startObjectTracking) {
			$this->createdObjects[] = $result;
		}
		return $result;
	}

	/**
	 * Creates a "allow all" property mapping configuration for all properties, even recursively.
	 *
	 * @param array <string> $propertyPaths
	 * @param string $className
	 * @return PropertyMappingConfiguration
	 */
	public function getPropertyMappingConfigurationForPropertyPaths(array $propertyPaths, $className = '')
	{
		$propertyMappingConfiguration = new PropertyMappingConfiguration();
		$propertyMappingConfiguration->allowAllProperties();
		$propertyMappingConfiguration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED, true);

		foreach ($propertyPaths as $propertyPath) {
			$propertyMappingConfiguration->forProperty($propertyPath)->allowAllProperties();
			$propertyMappingConfiguration->forProperty($propertyPath)->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED, true);
		}
		return $propertyMappingConfiguration;
	}

	/**
	 * The input structure needs to be a key=>value map.
	 * Every key should be a dot notation property path, every value the
	 * corresponding value.
	 *
	 * Example input in ".feature" file notation:
	 *     | foo -> json_encode() | ["bar"] |
	 *     | foo.2                | batz    |
	 *
	 * Example output in PHP code:
	 *     $result = ["foo" => [0 => "bar", 2: "batz"]]
	 *
	 * The output is an array of the resulting nested array structure
	 * and every property path contained.
	 *
	 * @param $row
	 * @return array
	 */
	public function getNestedStructureForDottedNotation(array $row)
	{
		$propertyPaths = [];

		$objectStructure = [];
		foreach ($row as $key => $value) {
			self::applyValueProcessors($key, $value);
			self::traverseObjectStructure($objectStructure, $propertyPaths, $key, $value);
		}

		return [$objectStructure, array_values($propertyPaths)];
	}

	/**
	 * The input structure is meant to be a key=>value map, the output structure
	 * will be one as well. To every key=>value pair, internal value processors
	 * such as "-> json_encode()" are applied.
	 *
	 * @param array $values
	 * @return array
	 */
	public function getFlatMap(array $values)
	{
		$result = [];
		foreach ($values as $key => $value) {
			self::applyValueProcessors($key, $value);
			$result[$key] = $value;
		}
		return $result;
	}

	/**
	 * The most recent object created gets remembered by a specific identifier
	 * as long as the current scenario runs.
	 *
	 * @param string $identifier
	 * @throws \Exception
	 */
	public function rememberLastObjectAs($identifier)
	{
		if (array_key_exists($identifier, $this->rememberedObjects)) {
			throw new \Exception(sprintf('The identifier "%s" is already taken', $identifier), 1480605866);
		}
		if (!$this->startObjectTracking) {
			throw new \Exception('Object tracking is not started, so remembering object is not possible.', 1480607437);
		}
		$object = end($this->createdObjects);
		$this->rememberedObjects[$identifier] = $object;
	}

	/**
	 * @param string $identifier
	 * @return mixed
	 * @throws \Exception
	 */
	public function getRememberedObject($identifier)
	{
		if (!$this->startObjectTracking) {
			throw new \Exception('Object tracking is not started, so remembering object is not possible.', 1480607455);
		}
		return $this->rememberedObjects[$identifier];
	}

	/**
	 * @param mixed $entity
	 * @param string $propertyPathString
	 * @param mixed $value
	 */
	public function setPropertyPath($entity, $propertyPathString, $value)
	{
		if (strpos($propertyPathString, '.') === false) {
			$subject = $entity;
			$propertyName = $propertyPathString;
		} else {
			$propertyPath = explode('.', $propertyPathString);
			$propertyName = array_pop($propertyPath);
			$propertyPath = join('.', $propertyPath);
			$subject = ObjectAccess::getPropertyPath($entity, $propertyPath);
		}
		ObjectAccess::setProperty($subject, $propertyName, $value);
	}

	/**
	 * @param array $objectStructure
	 * @param array $propertyPaths
	 * @param string $key
	 * @param string $value
	 */
	protected static function traverseObjectStructure(&$objectStructure, &$propertyPaths, $key, $value)
	{
		$keyParts = explode('.', $key);
		if (count($keyParts) > 1) {
			$currentPosition = &$objectStructure;
			foreach ($keyParts as $keyPart) {
				if (!array_key_exists($keyPart, $currentPosition)) {
					$currentPosition[$keyPart] = array();
				}
				$currentPosition = &$currentPosition[$keyPart];
			}
			$currentPosition = $value;
		} else {
			$objectStructure[$key] = $value;
		}

		while (count($keyParts)) {
			$propertyPath = join('.', $keyParts);
			$propertyPaths[$propertyPath] = $propertyPath;
			array_pop($keyParts);
		}
	}

	/**
	 * @param $key
	 * @param $value
	 */
	protected static function applyValueProcessors(&$key, &$value)
	{
		while (preg_match(self::METHOD_PATTERN, $key, $matches)) {
			$key = substr($key, 0, -1 * strlen($matches[0]));
			$processor = strtolower(sprintf('%s(%s)', $matches['method'], $matches['argument']));

			switch ($processor) {
				case 'hsc()':
				case 'htmlspecialchars()':
					$value = htmlspecialchars($value);
					break;
				case 'json_encode()':
					$value = json_encode($value);
					break;
				case 'json_decode()':
					$value = json_decode($value);
					break;
				case 'cast(string)':
				case 'convert(string)':
					$value = (string)($value);
					break;
				case 'cast(int)':
				case 'cast(integer)':
				case 'convert(int)':
				case 'convert(integer)':
					$value = (int)($value);
					break;
				case 'cast(bool)':
				case 'cast(boolean)':
				case 'convert(bool)':
				case 'convert(boolean)':
					$value = (bool)($value);
					break;
				case 'cast(float)':
				case 'convert(float)':
					$value = (float)($value);
					break;
				case 'cast(datetime)':
				case 'convert(datetime)':
					$value = new \DateTime($value);
					break;
			}
		}
	}
}