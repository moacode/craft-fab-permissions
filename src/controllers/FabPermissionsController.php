<?php

namespace thejoshsmith\fabpermissions\controllers;

use thejoshsmith\fabpermissions\FabPermissions;
use thejoshsmith\fabpermissions\records\FabPermissionsRecord;

use Craft;
use craft\web\Controller;
use yii\base\Exception;

/**
 * Fab Permissions Controller
 *
 * @author    Josh Smith
 * @package   fabpermissions
 * @since     1.0.0
 */
class FabPermissionsController extends Controller
{
    /**
     * Returns a list of user groups
     * @author Josh Smith <me@joshsmith.dev>
     * @return array
     */
    public function actionGetUserGroups()
    {
        // Fetch all user groups
        $userGroupsService = Craft::$app->getUserGroups();
        $groups = $userGroupsService->getAllGroups();

        return $this->asJson(['data' =>  ['userGroups' => $groups]]);
    }

    /**
     * Returns a list of tabs and fields with user group permissions
     * @author Josh Smith <me@joshsmith.dev>
     * @return array
     */
    public function actionGetFieldAndTabPermissions()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $layoutId = $request->post('fieldLayoutId');
        $fabService = FabPermissions::$plugin->fabService;

        if( empty($layoutId) ){
            return $this->asJson([
                'data' => [
                    'tabs' => [],
                    'fields' => []
                ]
            ]);
        }

        // Fetch the layout
        $layout = Craft::$app->getFields()->getLayoutById($layoutId);

        $tabsData = [];
        foreach ($layout->getTabs() as $tab) {

            // Set the tab name into the data array
            $tabsData[$tab->name] = [];

            // Fetch permissions for this tab
            $tabPermissions = $fabService->getPermissions([
                'layoutId' => $layoutId,
                'tabName' => $tab->name
            ]);

            // Loop permission records and assign to tab data
            foreach ($tabPermissions as $permission) {
                try {
                    $userGroupHandle = $this->_getUserGroupHandleFromPermission($permission);
                } catch(\Exception $e) {
                    continue;
                }

                $tabsData[$tab->name][$userGroupHandle] = [
                    'id' => $permission->id ?? '',
                    $fabService::$viewPermissionHandle => $permission->hasViewPermission(),
                    $fabService::$editPermissionHandle => $permission->hasEditPermission(),
                ];
            }
        }

        $fieldsData = [];
        $fieldPermissions = $fabService->getPermissions([
            'layoutId' => $layoutId,
            'tabName' => null
        ]);

        // Loop permission records and assign to tab data
        foreach ($fieldPermissions as $permission) {
            try {
                $userGroupHandle = $this->_getUserGroupHandleFromPermission($permission);
            } catch(Exception $e) {
                continue;
            }

            $fieldsData[$permission->fieldId][$userGroupHandle] = [
                'id' => $permission->id ?? '',
                $fabService::$viewPermissionHandle => $permission->hasViewPermission(),
                $fabService::$editPermissionHandle => $permission->hasEditPermission(),
            ];
        }

        return $this->asJson([
            'data' => [
                'tabs' => $tabsData,
                'fields' => $fieldsData
            ]
        ]);
    }

    /**
     * Returns a user group handle from the passed permission record
     * @author Josh Smith <me@joshsmith.dev>
     * @param  FabPermissionsRecord $permission
     * @return string
     */
    private function _getUserGroupHandleFromPermission(FabPermissionsRecord $permission): string
    {
        $fabService = FabPermissions::$plugin->fabService;

        if( is_null($permission->userGroupId) ){
            return $fabService::$adminPermissionHandle;
        }

        $userGroup = $permission->getUserGroup();
        if( empty($userGroup) ){
            throw new Exception('Failed to find user group.');
        }

        return $userGroup->handle;
    }
}
