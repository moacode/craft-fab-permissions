<?php
/**
 * Control Panel Permissions plugin for Craft CMS 3.x
 *
 * A plugin that allows admins to set tab and field restrictions for particular user groups in the system. For example, an admin could create a tabbed section that only they could see when creating entries.
 *
 * @link      https://joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace thejoshsmith\fabpermissions\services;

use Craft;
use craft\base\Component;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\web\User;

use thejoshsmith\fabpermissions\records\FabPermissions as FabPermissionRecord;

/**
 * Fab Permissions Service
 * @author    Josh Smith
 * @package   FabPermissions
 * @since     1.0.0
 */
class Fab extends Component
{
    // const DEFAULT_PERMISSION = '1';

    // public function getPermission($criteria = [])
    // {
    //     $currentSite = Craft::$app->sites->getCurrentSite();
    //     $criteria['siteId'] = $currentSite->id;
    //     $fabPermission = FabPermissionRecord::findOne($criteria);

    //     return (empty($fabPermission) ? null : $fabPermission->getAttribute('permission'));
    // }

    public function getPermissions($criteria = []) : array
    {
        $currentSite = Craft::$app->sites->getCurrentSite();
        $criteria['siteId'] = $currentSite->id;
        $fabPermissions = FabPermissionRecord::findAll($criteria);

        return (empty($fabPermissions) ? [] : $fabPermissions);
    }

    /**
     * Returns whether the passed user has permission to view the passed tab for the current site.
     * @author Josh Smith <josh.smith@platocreative.co.nz>
     * @param  FieldLayoutTab $tab         Tab object
     * @param  User           $user        User object
     * @param  Site           $currentSite Site object
     * @return boolean
     */
    public function hasTabPermission(FieldLayoutTab $tab, User $user, $currentSite = null)
    {
        if( $user->getIsAdmin() ) return true;
        if( $currentSite === null ) $currentSite = Craft::$app->sites->getCurrentSite();

        // Fetch permission records
        $fabPermissions = FabPermissionRecord::findAll([
            'layoutId' => $tab->getLayout()->id,
            'tabId' => $tab->id,
            'siteId' => $currentSite->id
        ]);

        // Loop the permissions and determine if the user can see the tab
        foreach ($fabPermissions as $fabPermission) {
            $isUserInGroup = $user->getIdentity()->isInGroup($fabPermission->userGroupId);
            if( $isUserInGroup && (bool) $fabPermission->permission === true ){
                return true;
            }
        }

        return false;
    }

    /**
     * Saves permissions from the passed field layout object
     * @author Josh Smith <josh.smith@platocreative.co.nz>
     * @param  FieldLayout $layout Field layout object
     * @return void
     */
    public function saveFieldLayoutPermissions(FieldLayout $layout)
    {
        $request = Craft::$app->getRequest();
        $postData = $request->post('tabPermissions');

        // we can't continue if there's no post data
        if( empty($postData) ) return false;

        $fabPermissionsData = [];
        $currentSite = Craft::$app->sites->getCurrentSite();

        // Loop tabs and work out permissions
        foreach ($layout->getTabs() as $tab) {
            foreach ($postData as $tabName => $permissions) {

                if( urldecode($tabName) !== $tab->name ) continue;

                foreach ($permissions as $handle => $value) {
                    $fabPermissionsData[] = [
                        $layout->id,
                        $tab->id,
                        $currentSite->id,
                        Craft::$app->getUserGroups()->getGroupByHandle($handle)->id,
                        $value
                    ];
                }
            }
        }

        // Determine the fields to use
        $fabPermissionsRecord = new FabPermissionRecord();
        $fields = array_values(
            array_intersect($fabPermissionsRecord->attributes(), [
                'layoutId',
                'tabId',
                // 'fieldId',
                'siteId',
                'userGroupId',
                'permission',
            ]
        ));

        if( !empty($fabPermissionsData) ){
            Craft::$app->db->createCommand()->batchInsert(FabPermissionRecord::tableName(), $fields, $fabPermissionsData)->execute();
        }
    }
}
