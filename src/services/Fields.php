<?php

namespace thejoshsmith\fabpermissions\services;

use Craft;
use craft\services\Fields as CraftFieldsService;
use craft\base\FieldInterface;

use thejoshsmith\fabpermissions\FabPermissions;
use thejoshsmith\fabpermissions\decorators\StaticFieldDecorator;

/**
 * Fields service.
 * An instance of the Fields service is globally accessible in Craft via [[\craft\base\ApplicationTrait::getFields()|`Craft::$app->fields`]].
 */
class Fields extends CraftFieldsService
{
    /**
     * Returns the fields in a field layout, identified by its ID.
     *
     * @param int $layoutId The field layout’s ID
     * @return FieldInterface[] The fields
     */
    public function getFieldsByLayoutId(int $layoutId): array
    {
        $fields = parent::getFieldsByLayoutId($layoutId);
        if( !$this->_shouldCheckPermissions() ) return $fields;

        $user = Craft::$app->getUser();
        $fabService = FabPermissions::$plugin->fabService;

        // Check if this user has permissions to view this field
        foreach ($fields as $i => $field) {
            if( !$fabService->canViewField($layoutId, $field, $user) ){
                unset($fields[$i]);
            } else {
                if( !$fabService->canEditField($layoutId, $field, $user) ){
                    $fields[$i] = new StaticFieldDecorator($field);
                }
            }
        }

        return $fields;
    }

    /**
     * Returns a layout's tabs by its ID.
     *
     * @param int $layoutId The field layout’s ID
     * @return FieldLayoutTab[] The field layout’s tabs
     */
    public function getLayoutTabsById(int $layoutId): array
    {
        $tabs = parent::getLayoutTabsById($layoutId);
        if( !$this->_shouldCheckPermissions() ) return $tabs;

        $user = Craft::$app->getUser();
        $fabService = FabPermissions::$plugin->fabService;

        // Check if this user has permissions to view this tab
        foreach ($tabs as $i => $tab) {
            if( !$fabService->canViewTab($tab, $user) ){
                unset($tabs[$i]);
            } elseif (isset($tab->elements)) {
                foreach ($tab->elements as $j => $element) {
                    if (!($element instanceof \craft\fieldlayoutelements\CustomField)) {
                        continue;
                    }
                    $field = $element->getField();
                    if (!$fabService->canViewField($layoutId, $field, $user)) {
                        unset($tab->elements[$j]);
                    } elseif (!$fabService->canEditField($layoutId, $field, $user)) {
                        $element->setField(new StaticFieldDecorator($field));
                    }
                }
            }
        }

        return $tabs;
    }

    /**
     * Determines whether the service should check permissions
     * @return boolean
     */
    private function _shouldCheckPermissions(): bool
    {
        if( !$this->_isFabPermissionsRunning() ) return false;

        $fabService = FabPermissions::$plugin->fabService;
        if( !$fabService->isSupportedRequest() ) return false;

        return true;
    }

    /**
     * Returns true if the plugin is installed and enabled
     * We need to do this check here as this service will be overriden in app config, 
     * regardless of whether the plugin is actually installed/enabled or not.
     * @return boolean
     */
    private function _isFabPermissionsRunning(): bool
    {
        $plugins = Craft::$app->getPlugins();
        $isPluginInstalled = $plugins->isPluginInstalled(FabPermissions::PLUGIN_HANDLE);
        $isPluginEnabled = $plugins->isPluginEnabled(FabPermissions::PLUGIN_HANDLE);        

        return $isPluginInstalled && $isPluginEnabled;
    }
}
