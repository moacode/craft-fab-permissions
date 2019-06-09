<?php

namespace thejoshsmith\fabpermissions\controllers;

use thejoshsmith\fabpermissions\fabpermissions;

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
        $userGroupsService = Craft::$app->getUserGroups();
        $groups = $userGroupsService->getAllGroups();

        return [
            'data' =>  [
                'userGroups' => $groups
            ]
        ];
    }
}
