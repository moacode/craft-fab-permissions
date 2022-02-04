<?php

namespace thejoshsmith\fabpermissions\services;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\models\FieldLayout;
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
     * @var
     */
    private $_layoutsById;

    /**
     * Returns field layouts by their IDs.
     *
     * @param int[] $layoutIds The field layouts’ IDs
     * @return FieldLayout[] The field layouts
     * @since 3.7.27
     */
    public function getLayoutsByIds(array $layoutIds): array
    {
        $response = [];

        // Don't re-fetch any layouts we've already memoized
        if (isset($this->_layoutsById)) {
            foreach ($layoutIds as $key => $id) {
                if (array_key_exists($id, $this->_layoutsById)) {
                    if ($this->_layoutsById[$id] !== null) {
                        $response[$id] = $this->_layoutsById[$id];
                    }
                    unset($layoutIds[$key]);
                }
            }
        }

        if (!empty($layoutIds)) {
            $result = $this->_createLayoutQuery()
                ->andWhere(['id' => $layoutIds])
                ->all();

            $layouts = [];

            foreach ($result as $row) {
                $this->_layoutsById[$row['id']] = $response[$row['id']] = $layouts[$row['id']] = new FieldLayout($row);
            }

            $this->_loadTabs($layouts);
        }

        return $response;
    }

    /**
     * Returns a Query object prepped for retrieving layouts.
     *
     * @return Query
     */
    private function _createLayoutQuery(): Query
    {
        $query = (new Query)
            ->select([
                'id',
                'type',
                'uid',
            ])
            ->from([Table::FIELDLAYOUTS]);

        // todo: remove schema version condition after next beakpoint
        $schemaVersion = Craft::$app->getInstalledSchemaVersion();
        if (version_compare($schemaVersion, '3.1.0', '>=')) {
            $query->where(['dateDeleted' => null]);
        }

        return $query;
    }

    /**
     * Fetches the layout tabs for the given layouts.
     *
     * @param FieldLayout[] $layouts Field layouts indexed by their IDs
     */
    private function _loadTabs(array $layouts): void
    {
        if (empty($layouts)) {
            return;
        }

        $tabsByLayoutId = [];
        foreach(array_keys($layouts) as $layoutId){
            $tabsByLayoutId[$layoutId] = $this->getLayoutTabsById($layoutId);
        }

        foreach ($tabsByLayoutId as $layoutId => $tabs) {
            $layouts[$layoutId]->setTabs($tabs);
        }
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
                continue;
            }

            // Loop tab elements and apply permissions to custom fields
            // Todo: investigate what can be done with custom elements
            foreach ($tab->elements as $i => $element) {

                if(is_a($element, "craft\\fieldlayoutelements\\EntryTitleField")){
                    if($user->getIdentity()->isInGroup('readOnly')) {
                        $element->disabled = true;
                    }
                }

                if( is_a($element, "craft\\fieldlayoutelements\\CustomField") ){
                    $field = $element->getField();

                    if( !$fabService->canViewField($layoutId, $field, $user) ){
                        unset($tab->elements[$i]);
                    } else {
                        if( !$fabService->canEditField($layoutId, $field, $user) ){
                            $element->setField(new StaticFieldDecorator($field));
                        }
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
