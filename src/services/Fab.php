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
use craft\base\Field;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\events\FieldEvent;
use craft\events\FieldLayoutEvent;
use craft\events\SiteEvent;
use craft\events\UserGroupEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\records\FieldLayoutTab as FieldLayoutTabRecord;
use craft\web\User;
use yii\base\Exception;

use thejoshsmith\fabpermissions\records\FabPermissionsRecord;

/**
 * Fab Permissions Service
 * @author    Josh Smith
 * @package   FabPermissions
 * @since     1.0.0
 */
class Fab extends Component
{
    const CONFIG_KEY = 'fieldAndTabPermissions';

    /**
     * Define the permission handles
     * @var string
     */
    public static $adminPermissionHandle = 'admin';
    public static $viewPermissionHandle = 'canView';
    public static $editPermissionHandle = 'canEdit';

    /**
     * Returns Fab Permission records matching the passed criteria
     * @author Josh Smith <me@joshsmith.dev>
     * @param  array  $criteria An array of criteria filters
     * @return array
     */
    public function getPermissions($criteria = []) : array
    {
        $currentSite = Craft::$app->sites->getCurrentSite();
        $criteria['siteId'] = $currentSite->id;
        $fabPermissions = FabPermissionsRecord::findAll($criteria);

        return (empty($fabPermissions) ? [] : $fabPermissions);
    }

    /**
     * Returns whether the passed user has permission to view the passed tab for the current site.
     * @author Josh Smith <me@joshsmith.dev>
     * @param  FieldLayoutTab $tab         Tab object
     * @param  User           $user        User object
     * @param  Site           $currentSite Site object
     * @return boolean
     */
    public function canViewTab(FieldLayoutTab $tab, User $user, $currentSite = null)
    {
        if( $user->getIsAdmin() ) return true;
        if( $user->getIsGuest() ) return false;
        if( $currentSite === null ) $currentSite = Craft::$app->sites->getCurrentSite();

        // Fetch permission records
        $fabPermissions = FabPermissionsRecord::findAll([
            'layoutId' => $tab->getLayout()->id,
            'tabName' => $tab->name,
            'siteId' => $currentSite->id
        ]);

        // Return true if no permissions have been set on this tab
        if( empty($fabPermissions) ) return true;

        // Loop the permissions and determine if the user can see the tab
        foreach ($fabPermissions as $fabPermission) {
            $isUserInGroup = $user->getIdentity()->isInGroup($fabPermission->userGroupId);
            if( $isUserInGroup && (bool) $fabPermission->{self::$viewPermissionHandle} === true ){
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the passed user has permission to view the passed field for the current site
     * @author Josh Smith <me@joshsmith.dev>
     * @param  int     $layoutId    Layout ID
     * @param  Field   $field       Field object
     * @param  User    $user        User object
     * @param  Site    $currentSite Site object
     * @return boolean
     */
    public function canViewField(int $layoutId, Field $field, User $user, $currentSite = null)
    {
        if( $user->getIsAdmin() ) return true;
        if( $user->getIsGuest() ) return false;
        if( $currentSite === null ) $currentSite = Craft::$app->sites->getCurrentSite();

         // Fetch permission records
        $fabPermissions = FabPermissionsRecord::findAll([
            'layoutId' => $layoutId,
            'fieldId' => $field->id,
            'siteId' => $currentSite->id
        ]);

        // Return true if no permissions have been set on this tab
        if( empty($fabPermissions) ) return true;

        // Loop the permissions and determine if the user can see the tab
        foreach ($fabPermissions as $fabPermission) {
            $isUserInGroup = $user->getIdentity()->isInGroup($fabPermission->userGroupId);
            if( $isUserInGroup && (bool) $fabPermission->{self::$viewPermissionHandle} === true ){
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the passed user has permission to view the passed field for the current site
     * @author Josh Smith <me@joshsmith.dev>
     * @param  int     $layoutId    Layout ID
     * @param  Field   $field       Field object
     * @param  User    $user        User object
     * @param  Site    $currentSite Site object
     * @return boolean
     */
    public function canEditField(int $layoutId, Field $field, User $user, $currentSite = null)
    {
        if( $user->getIsAdmin() ) return true;
        if( $currentSite === null ) $currentSite = Craft::$app->sites->getCurrentSite();

         // Fetch permission records
        $fabPermissions = FabPermissionsRecord::findAll([
            'layoutId' => $layoutId,
            'fieldId' => $field->id,
            'siteId' => $currentSite->id
        ]);

        // Return true if no permissions have been set on this tab
        if( empty($fabPermissions) ) return true;

        // Loop the permissions and determine if the user can see the tab
        foreach ($fabPermissions as $fabPermission) {
            $isUserInGroup = $user->getIdentity()->isInGroup($fabPermission->userGroupId);
            if( $isUserInGroup && (bool) $fabPermission->{self::$editPermissionHandle} === true ){
                return true;
            }
        }

        return false;
    }

    /**
     * Saves permissions from the passed field layout object
     * @author Josh Smith <me@joshsmith.dev>
     * @param  FieldLayout $layout Field layout object
     * @return void
     */
    public function saveFieldLayoutPermissions(FieldLayout $layout)
    {
        $uids = [];
        $request = Craft::$app->getRequest();
        $hasPostData = $request->post('tabPermissions') || $request->post('fieldPermissions');
        $tabPermissions = $request->post('tabPermissions') ?? [];
        $fieldPermissions = $request->post('fieldPermissions') ?? [];

        // The front end didn't finish loading before it was saved
        // TODO: Remove this once the below has been addressed.
        if( $request->post('fabPermissionsAbort') ){
            return;
        }

        // No POST? Clean up all permissions just to be safe
        // TODO: use joins to clear stale information and run instead of deletePermissions().
        if( empty($hasPostData) ){
            return $this->deletePermissions($layout->id);
        };

        foreach ($layout->getTabs() as $tab) {
            foreach ($tabPermissions as $tabName => $permissions) {
                // Skip if this isn't the right tab
                if( urldecode($tabName) !== $tab->name ) continue;

                // Save tab permissions and merge UIDs
                $tabUids = $this->savePermissions($layout->id, $tab->name, null, $permissions);
                $uids = array_merge($uids, $tabUids);
            }
        }

        foreach ($fieldPermissions as $fieldId => $permissions) {
            // Save field permissions and store UIDs
            $fieldUids = $this->savePermissions($layout->id, null, $fieldId, $permissions);
            $uids = array_merge($uids, $fieldUids);
        }

        // Ensure stale permissions records are removed
        // We do this by passing in the list of valid uuids for this field layout
        $this->removeStalePermissionsByUids($layout->id, $uids);
    }

    /**
     * Removes stale permissions for the passed layout by an array of UIDs
     * Permissions are considered stale if they are not in the UID array
     * @author Josh Smith <josh@batch.nz>
     * @param  int    $layoutId
     * @param  array  $uids
     * @return void
     */
    public function removeStalePermissionsByUids(int $layoutId, array $uids = [])
    {
        if( empty($uids) ) return;

        // Remove stale permissions that were cleared
        $staleRecords = FabPermissionsRecord::find()
            ->select(['uid'])
            ->where(['NOT IN', 'uid', $uids])
            ->andWhere(['layoutId' => $layoutId])
            ->all();

        if( !empty($staleRecords) ){
            foreach ($staleRecords as $staleRecord) {
                $path = self::CONFIG_KEY.'.'.$staleRecord['uid'];
                Craft::$app->projectConfig->remove($path);
            }
        }
    }

    /**
     * Saves permissions and writes to project config
     * @author Josh Smith <josh@batch.nz>
     * @param  int         $layoutId    ID of the field layout
     * @param  string|null $tabName     Name of the tab
     * @param  int|null    $fieldId     Field ID
     * @param  array       $permissions An array of permissions in `'permissionHandle' => true` format
     * @return array                    An array of UUIDs
     */
    public function savePermissions(int $layoutId, string $tabName = null, int $fieldId = null, array $permissions)
    {
        $uids = [];
        $currentSite = Craft::$app->sites->getCurrentSite();

        foreach ($permissions as $handle => $perms) {

            // Fetch or generate a UID for this record
            $uid = empty($perms['id']) ?
                $uid = StringHelper::UUID() :
                Db::uidById(FabPermissionsRecord::tableName(), $perms['id']);

            // Work out the user groups and permissions
            $uids[] = $uid;
            $userGroupId = $this->getUserGroupIdFromHandle($handle);
            $canViewValue = ($userGroupId === null ? '1' : $perms[self::$viewPermissionHandle]);
            $canEditValue = ($userGroupId === null || !empty($tabName) ? '1' : $perms[self::$editPermissionHandle]);

            $config = [
                'layoutId' => $layoutId,
                'tabName' => $tabName,
                'fieldId' => $fieldId,
                'siteId' => $currentSite->id,
                'userGroupId' => $userGroupId,
                self::$viewPermissionHandle => (isset($canViewValue) ? $canViewValue : '0'),
                self::$editPermissionHandle => (isset($canEditValue) ? $canEditValue : '0')
            ];

            // Write to project config
            $path = self::CONFIG_KEY.'.'.$uid;
            Craft::$app->projectConfig->set($path, $config);
        }

        return $uids;
    }

    /**
     * Deletes permissions for the passed layoutId
     * @author Josh Smith <josh@batch.nz>
     * @param  int $layoutId
     * @return void
     */
    public function deletePermissions(int $layoutId)
    {
        $results = (new Query())
            ->select(['uid'])
            ->from(FabPermissionsRecord::tableName())
            ->where(['layoutId' => $layoutId])
        ->all();

        if( empty($results) ) return;

        foreach ($results as $result) {
            $path = self::CONFIG_KEY.'.'.$result['uid'];
            Craft::$app->projectConfig->remove($path);
        }
    }

    /**
     * Assembles the full project config data based on the current database state
     * @author Josh Smith <josh@batch.nz>
     * @return array
     */
    public function assembleProjectConfigData()
    {
        $records = FabPermissionsRecord::find()->all();
        if( empty($records) ) return;

        $data = [];
        foreach ($records as $record) {
            foreach ($record as $key => $value) {
                if( in_array($key, ['dateCreated', 'dateUpdated', 'uid']) ) continue;
                $data[self::CONFIG_KEY][$record['uid']][$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Handles changed project config permissions
     * @author Josh Smith <josh@batch.nz>
     * @since  1.5.0 Added project config support
     * @param  ConfigEvent $event
     * @return void
     */
    public function handleChangedPermission(ConfigEvent $event)
    {
        // Get the UID that was matched in the config path
        $uid = $event->tokenMatches[0];

        // Does this permission exist?
        $id = (new Query())
            ->select(['id'])
            ->from(FabPermissionsRecord::tableName())
            ->where(['uid' => $uid])
            ->scalar();

        $isNew = empty($id);

        // Insert or update its row
        if ($isNew) {
            Craft::$app->db->createCommand()
                ->insert(FabPermissionsRecord::tableName(), array_merge($event->newValue, ['uid' => $uid]))
            ->execute();
        } else {
            Craft::$app->db->createCommand()
                ->update(FabPermissionsRecord::tableName(), $event->newValue, ['id' => $id])
            ->execute();
        }
    }

    /**
     * Handles deleted project config permissions
     * @author Josh Smith <josh@batch.nz>
     * @since  1.5.0 Added project config support
     * @param  ConfigEvent $event
     * @return void
     */
    public function handleDeletedPermission(ConfigEvent $event)
    {
        // Get the UID that was matched in the config path
        $uid = $event->tokenMatches[0];

        // Get the permission record
        $permission = FabPermissionsRecord::find()
            ->where(['uid' => $uid])
        ->one();

        // If that came back empty, we're done!
        if (!$permission) {
            return;
        }

        // Delete its row
        Craft::$app->db->createCommand()
            ->delete(FabPermissionsRecord::tableName(), ['id' => $permission->id])
        ->execute();
    }

    /**
     * Handles the deletion of a field
     * @author Josh Smith <josh@batch.nz>
     * @param  FieldEvent $event
     * @return void
     */
    public function handleDeletedField(FieldEvent $event)
    {
        $results = (new Query())
            ->select(['uid'])
            ->from(FabPermissionsRecord::tableName())
            ->where(['fieldId' => $event->field->id])
        ->all();

        if( empty($results) ) return;

        foreach ($results as $result) {
            $path = self::CONFIG_KEY.'.'.$result['uid'];
            Craft::$app->projectConfig->remove($path);
        }
    }

    /**
     * Handles the deletion of a site
     * @author Josh Smith <josh@batch.nz>
     * @param  SiteEvent $event
     * @return void
     */
    public function handleDeletedSite(SiteEvent $event)
    {
        $results = (new Query())
            ->select(['uid'])
            ->from(FabPermissionsRecord::tableName())
            ->where(['siteId' => $event->site->id])
        ->all();

        if( empty($results) ) return;

        foreach ($results as $result) {
            $path = self::CONFIG_KEY.'.'.$result['uid'];
            Craft::$app->projectConfig->remove($path);
        }
    }

    /**
     * Removes all layout permissions from the database
     * @author Josh Smith <josh@batch.nz>
     * @param  FieldLayoutEvent $event
     * @return void
     */
    public function handleDeletedLayout(FieldLayoutEvent $event)
    {
        return $this->deletePermissions($event->layout->id);
    }

    /**
     * Handles the deletion of a user group
     * @author Josh Smith <josh@batch.nz>
     * @param  UserGroupEvent $event
     * @return void
     */
    public function handleDeletedUserGroup(UserGroupEvent $event)
    {
        $results = (new Query())
            ->select(['uid'])
            ->from(FabPermissionsRecord::tableName())
            ->where(['userGroupId' => $event->userGroup->id])
        ->all();

        if( empty($results) ) return;

        foreach ($results as $result) {
            $path = self::CONFIG_KEY.'.'.$result['uid'];
            Craft::$app->projectConfig->remove($path);
        }
    }

    /**
     * Returns the user group ID from the passed handle
     * @author Josh Smith <me@joshsmith.dev>
     * @param  string $handle User group handle
     * @return integer
     */
    public function getUserGroupIdFromHandle($handle)
    {
        // Admin handle is special, and is inserted as a null value
        if( $handle === self::$adminPermissionHandle ) return $groupHandleId = null;

        // Fetch the user group
        $group = Craft::$app->getUserGroups()->getGroupByHandle($handle);
        if( empty($group) ) throw new Exception('Invalid user group handle.');

        return $group->id;
    }

    /**
     * Returns whether the current Craft request is supported for parsing permissions
     * @since 1.4.0
     * @author Josh Smith <me@joshsmith.dev>
     * @return boolean
    */
    public function isSupportedRequest()
    {
        $user = Craft::$app->user;
        $request = Craft::$app->getRequest();
        $isCpRequest = Craft::$app->getRequest()->getIsCpRequest();

        // All Control Panel requests are supported
        if( $isCpRequest ) {
            return true;
        }

        // Front end POST requests for logged in users are supported
        // This means that permissions on field layouts will apply to logged in users on form submissions etc.
        if( !$isCpRequest && !$user->getIsGuest() && $request->getIsPost() ){
            return true;
        }

        // All other requests are not supported
        return false;
    }
}
