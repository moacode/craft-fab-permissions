<?php

namespace thejoshsmith\fabpermissions\services;

use Craft;
use craft\services\Fields as CraftFieldsService;
use thejoshsmith\fabpermissions\FabPermissions;

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
        $user = Craft::$app->getUser();
        $tabs = parent::getLayoutTabsById($layoutId);
        $fabService = FabPermissions::$plugin->fabService;

        // Check if this user has permissions to view this tab
        foreach ($tabs as $i => $tab) {
            if( !$fabService->hasTabPermission($tab, $user) ){
                unset($tabs[$i]);
            }
        }

        return $tabs;
    }
}
