<?php

namespace thejoshsmith\fabpermissions\records;

use Craft;
use craft\db\ActiveRecord;
use thejoshsmith\fabpermissions\FabPermissions;

class FabPermissionsRecord extends ActiveRecord
{
    /**
     * Define constants
     */
    const TABLE_NAME = 'fabpermissions_fieldlayoutpermissions';

    // Public Static Methods
    // =========================================================================

     /**
     * Declares the name of the database table associated with this AR class.
     * @return string the table name
     */
    public static function tableName()
    {
        return '{{%'.self::TABLE_NAME.'}}';
    }

    /**
     * Returns the associated user group
     * @author Josh Smith <me@joshsmith.dev>
     * @return UserGroup
     */
    public function getUserGroup()
    {
        $userGroupService = Craft::$app->getUserGroups();
        return $userGroupService->getGroupById($this->userGroupId);
    }

    /**
     * Returns the associated permission
     * @author Josh Smith <me@joshsmith.dev>
     * @return boolean
     */
    public function hasViewPermission()
    {
        $viewPermissionHandle = FabPermissions::$plugin->fabService::$viewPermissionHandle;
        return (bool) $this->{$viewPermissionHandle};
    }

    /**
     * Returns whether this record is readonly
     * @author Josh Smith <me@joshsmith.dev>
     * @return boolean
     */
    public function hasEditPermission()
    {
        $editPermissionHandle = FabPermissions::$plugin->fabService::$editPermissionHandle;
        return (bool) $this->{$editPermissionHandle};
    }
}
