<?php

namespace thejoshsmith\fabpermissions\controllers;

use thejoshsmith\fabpermissions\FabPermissions;

use Craft;
use yii\rest\Controller;

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
     * @author Josh Smith <josh.smith@platocreative.co.nz>
     * @return array
     */
    public function actionGetUserGroups()
    {
        $request = Craft::$app->getRequest();
        $layoutId = $request->post('fieldLayoutId');
        $tabName = $request->post('tabName');

        // Parse out the tab ID from the tab name
        if( $layoutId ){
            $layout = Craft::$app->getFields()->getLayoutById($layoutId);
            foreach ($layout->getTabs() as $tab) {
                if( $tab->name === $tabName ) $tabId = $tab->id;
            }
        }

        // Fetch all user groups
        $userGroupsService = Craft::$app->getUserGroups();
        $groups = $userGroupsService->getAllGroups();

        $userGroupsData = [];
        foreach ($groups as $group) {

            $groupArray = $group->toArray();

            $groupArray['permission'] = FabPermissions::$plugin->fabService->getPermission([
                'userGroupId' => $group->id,
                'layoutId' => $layoutId,
                'tabId' => $tabId ?? null,
            ]);

            $userGroupsData[] = $groupArray;
        }

        return [
            'data' =>  [
                'userGroups' => $userGroupsData
            ]
        ];
    }
}
