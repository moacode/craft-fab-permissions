<?php

namespace thejoshsmith\fabpermissions\base;

/**
 * DecoratorAbstract allows the creation of flexible nested decorators. All decorators must extend from DecoratorAbstract.
 * Decorators can be stacked. They can also have methods that overwrite each other.
 * Decorators can omit methods that parent decorators have defined and/or child decorators have defined.
 * Methods will cascade to the original child object.
 * Properties will read and set from the original child object except when your instance has the property defined.
 * @see  https://gist.github.com/CMCDragonkai/6896642
 */
abstract class Decorator {
	protected $object;

	public function __construct($object)
	{
		$this->object = $object;
	}

	/**
	 * Gets the original object that all the decorators have wrapped themselves around.
	 * @return Object
	 */
	public function get_original_object(){

		$object = $this->object;

		while(is_a($object, get_class())){
			$object = $object->get_original_object();
		}

		return $object;
	}

	/**
	 * Magic __call will recursively call itself and cascade through all the methods on the decorators.
	 * This will work for the child object's methods, and even when methods are missing in between the decorator stack.
	 * @param  String $method
	 * @param  Array  $args
	 * @return Mixed
	 */
	public function __call($method, $args){
		if( !method_exists($this->object, $method) ) return;
		return call_user_func_array(array($this->object, $method), $args);
	}

	/**
	 * Magic __get will return the properties from the original object.
	 * This won't be executed if the current instance has the property defined.
	 * @param  String $property
	 * @return Mixed
	 */
	public function __get($property){
		$object = $this->get_original_object();
		if(property_exists($object, $property)){
			return $object->$property;
		}
		return null;
	}

	/**
	 * Magic __isset will return whether the original object's property is isset or not
	 * @author Josh Smith <me@joshsmith.dev>
	 * @param  string  $name Property to check
	 * @return boolean
	 */
	public function __isset($name)
	{
		$object = $this->get_original_object();
		return isset($object->$name);
	}

	/**
	 * Magic __set will set a property on the original object.
	 * This won't be executed if the current instance has the property defined.
	 * @param  String $property
	 * @param  Mixed  $value
	 * @return Object $this
	 */
	public function __set($property, $value){
		$object = $this->get_original_object();
		$object->$property = $value;
		return $this;
	}
}