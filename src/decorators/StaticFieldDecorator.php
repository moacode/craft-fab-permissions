<?php

namespace thejoshsmith\fabpermissions\decorators;

use thejoshsmith\fabpermissions\base\Decorator;
use craft\base\FieldInterface;
use craft\base\ElementInterface;


class StaticFieldDecorator extends Decorator {

	public function __construct(FieldInterface $field)
    {
    	return parent::__construct($field);
    }

    /**
     * Proxy all requests for input html to get static html
     * @author Josh Smith <me@joshsmith.dev>
     * @param  string                $value
     * @param  ElementInterface|null $element
     * @return string
     */
	public function getInputHtml($value, ElementInterface $element = null): string
	{
		return parent::getStaticHtml($value, $element);
	}
}