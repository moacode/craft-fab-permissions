<?php

namespace thejoshsmith\fabpermissions\base;

use thejoshsmith\fabpermissions\base\Decorator;

use craft\base\Field;
use craft\base\FieldInterface;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;

/**
 * Abstract decorator that implements the field interface so decorated fields can be used internally
 */
abstract class FieldDecorator extends Decorator implements FieldInterface {

	// Component Interfave
	public static function displayName(): string
	{
		return Field::displayName();
	}

	// Savable Component Interface
	public static function isSelectable(): bool
	{
		return Field::isSelectable();
	}

	public function getIsNew(): bool
	{
		return parent::getIsNew();
	}

	public function validate($attributeNames = null, $clearErrors = true)
	{
		return parent::validate($attributeNames, $clearErrors);
	}

	public function settingsAttributes(): array
	{
		return parent::settingsAttributes();
	}

	public function getSettings(): array
	{
		return parent::getSettings();
	}

	public function getSettingsHtml()
	{
		return parent::getSettingsHtml();
	}

	public function beforeSave(bool $isNew): bool
	{
		return parent::beforeSave($isNew);
	}

	public function afterSave(bool $isNew)
	{
		return parent::afterSave($isNew);
	}

	public function beforeDelete(): bool
	{
		return parent::beforeDelete();
	}

	public function beforeApplyDelete()
	{
		return parent::beforeApplyDelete();
	}

	public function afterDelete()
	{
		return parent::afterDelete();
	}

	// FieldInterface
	public static function hasContentColumn(): bool
	{
		return false; // We won't ever be validating/saving this field, so this is safe.
	}

	public static function supportedTranslationMethods(): array
	{
		return Field::supportedTranslationMethods();
	}

	public static function valueType(): string
	{
		return Field::valueType();
	}

	public function getContentColumnType(): string
	{
		return parent::getContentColumnType();
	}

	public function getIsTranslatable(ElementInterface $element = null): bool
	{
		return parent::getIsTranslatable($element);
	}

	public function getTranslationDescription(ElementInterface $element = null)
	{
		return parent::getTranslationDescription($element);
	}

	public function getTranslationKey(ElementInterface $element): string
	{
		return parent::getTranslationKey($element);
	}

	public function getInputHtml($value, ElementInterface $element = null): string
	{
		return parent::getInputHtml($value, $element);
	}

	public function getStaticHtml($value, ElementInterface $element): string
	{
		return parent::getStaticHtml($value, $element);
	}

	public function getElementValidationRules(): array
	{
		return parent::getElementValidationRules();
	}

	public function isValueEmpty($value, ElementInterface $element): bool
	{
		return parent::isValueEmpty($value, $element);
	}

	public function getSearchKeywords($value, ElementInterface $element): string
	{
		return parent::getSearchKeywords($value, $element);
	}

	public function normalizeValue($value, ElementInterface $element = null)
	{
		return parent::normalizeValue($value, $element);
	}

	public function serializeValue($value, ElementInterface $element = null)
	{
		return parent::serializeValue($value, $element);
	}

	public function modifyElementsQuery(ElementQueryInterface $query, $value)
	{
		return parent::modifyElementsQuery($query, $value);
	}

	public function modifyElementIndexQuery(ElementQueryInterface $query)
	{
		return parent::modifyElementIndexQuery($query);
	}

	public function setIsFresh(bool $isFresh = null)
	{
		return parent::setIsFresh($isFresh);
	}

	public function getGroup()
	{
		return parent::getGroup();
	}

    public function getContentGqlType()
    {
        return parent::getContentGqlType();
    }

	public function beforeElementSave(ElementInterface $element, bool $isNew): bool
	{
		return parent::beforeElementSave($element, $isNew);
	}

	public function afterElementSave(ElementInterface $element, bool $isNew)
	{
		return parent::afterElementSave($element, $isNew);
	}

	public function afterElementPropagate(ElementInterface $element, bool $isNew)
	{
		return parent::afterElementPropagate($element, $isNew);
	}

	public function beforeElementDelete(ElementInterface $element): bool
	{
		return parent::beforeElementDelete($element);
	}

	public function afterElementDelete(ElementInterface $element)
	{
		return parent::afterElementDelete($element);
	}

	public function beforeElementRestore(ElementInterface $element): bool
	{
		return parent::beforeElementRestore($element);
	}

	public function afterElementRestore(ElementInterface $element)
	{
		return parent::afterElementRestore($element);
	}
}