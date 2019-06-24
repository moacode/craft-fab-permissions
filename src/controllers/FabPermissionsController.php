<?php

namespace thejoshsmith\fabpermissions\controllers;

use thejoshsmith\fabpermissions\FabPermissions;

use Craft;
use craft\web\Controller;

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
            $tabsData[urlencode($tab->name)] = [];

            // Fetch permissions for this tab
            $tabPermissions = $fabService->getPermissions([
                'layoutId' => $layoutId,
                'tabId' => $tab->id
            ]);

            // Loop permission records and assign to tab data
            foreach ($tabPermissions as $permission) {
                $userGroupHandle = (is_null($permission->userGroupId) ? $fabService::$adminPermissionHandle : $permission->getUserGroup()->handle);
                $tabsData[$tab->name][$userGroupHandle] = [
                    $fabService::$viewPermissionHandle => $permission->hasViewPermission(),
                    $fabService::$editPermissionHandle => $permission->hasEditPermission()
                ];
            }
        }

        $fieldsData = [];
        $fieldPermissions = $fabService->getPermissions([
            'layoutId' => $layoutId,
            'tabId' => null
        ]);

        // Loop permission records and assign to tab data
        foreach ($fieldPermissions as $permission) {
            $userGroupHandle = (is_null($permission->userGroupId) ? $fabService::$adminPermissionHandle : $permission->getUserGroup()->handle);
            $fieldsData[$permission->fieldId][$userGroupHandle] = [
                $fabService::$viewPermissionHandle => $permission->hasViewPermission(),
                $fabService::$editPermissionHandle => $permission->hasEditPermission()
            ];
        }

        return $this->asJson([
            'data' => [
                'tabs' => $tabsData,
                'fields' => $fieldsData
            ]
        ]);
    }
}
