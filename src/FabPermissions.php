<?php
/**
 * Field and Tab (Fab) Permissions plugin for Craft CMS 3.x
 * A plugin that allows admins to set tab and field restrictions for particular user groups in the system. For example, an admin could create a tabbed section that only they could see when creating entries.
 *
 * @link      https://joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace thejoshsmith\fabpermissions;

use thejoshsmith\fabpermissions\services\Fab as FabService;
use thejoshsmith\fabpermissions\services\Fields;
use thejoshsmith\fabpermissions\assetbundles\fabpermissions\FabPermissionsAsset;

use Craft;
use craft\base\Plugin;
use craft\events\FieldLayoutEvent;
use craft\events\ConfigEvent;
use craft\events\RebuildConfigEvent;
use craft\services\ProjectConfig;
use craft\services\Sites;
use craft\services\UserGroups;
use yii\base\Event;

/**
 * Fab Permissions Plugin
 * Allows the setting of user group permissions on tabs and fields wherever the Field Layout Designer is present.
 * Permissions are applied across multiple sites by default.
 *
 * @author    Josh Smith
 * @package   FabPermissions
 * @since     1.0.0
 *
 */
class FabPermissions extends Plugin
{
    const PLUGIN_HANDLE = 'craft-fab-permissions';

    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * FabPermissions::$plugin
     *
     * @var FabPermissions
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public $schemaVersion = '1.5.0';

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Register services
        $this->registerComponents();
        $this->registerProjectConfig();

        // Ensure we only init the plugin on CP requests.
        if( !Craft::$app->getRequest()->getIsCpRequest() ) return false;

        // Show a warning to the user if the component config hasn't been overriden.
        $fieldsService = Craft::$app->getFields();
        if( !is_a($fieldsService, 'thejoshsmith\\fabpermissions\\services\\Fields') ){
            Craft::$app->getSession()->setError('Fab Permissions Plugin: Please override the fields service in your app config - Check the README for more information.');
        }

        // Bootstrap this plugin
        $this->registerAssetBundles();
        $this->handleEvents();

        Craft::info(
            Craft::t(
                self::PLUGIN_HANDLE,
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * Registers asset bundles for the Control Panel
     * @author Josh Smith <me@joshsmith.dev>
     * @return void
     */
    protected function registerAssetBundles()
    {
        // register anasset bundle on Control Panel requests
        FabPermissionsAsset::register(Craft::$app->view);
    }

    /**
     * Registers Plugin Components
     * @author Josh Smith <me@joshsmith.dev>
     * @return void
     */
    protected function registerComponents()
    {
        Craft::$app->setComponents(['fabService' => FabService::class]);
    }

    protected function registerProjectConfig()
    {
         /**
         * Rebuilds the field and tabs permission project config data from the database
         */
        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $e) {
            $configData = $this->fabService->assembleProjectConfigData();

            if( !empty($configData) ){
                $e->config = $configData;
            }
        });

        // Listen for project config events
        Craft::$app->projectConfig
            ->onAdd('fieldAndTabPermissions.{uid}', [$this->fabService, 'handleChangedPermission'])
            ->onUpdate('fieldAndTabPermissions.{uid}', [$this->fabService, 'handleChangedPermission'])
            ->onRemove('fieldAndTabPermissions.{uid}', [$this->fabService, 'handleDeletedPermission']);

        // Remove permissions linked to deleted elements
        Event::on(Fields::class, Fields::EVENT_AFTER_DELETE_FIELD, [$this->fabService, 'handleDeletedField']);
        Event::on(Sites::class, Sites::EVENT_AFTER_DELETE_SITE, [$this->fabService, 'handleDeletedSite']);
        Event::on(Fields::class, Fields::EVENT_AFTER_DELETE_FIELD_LAYOUT, [$this->fabService, 'handleDeletedLayout']);
        Event::on(UserGroups::class, UserGroups::EVENT_AFTER_DELETE_USER_GROUP, [$this->fabService, 'handleDeletedUserGroup']);
    }

    /**
     * Attach event handlers
     * @author Josh Smith <me@joshsmith.dev>
     * @return void
     */
    protected function handleEvents()
    {
        // Process the saving of permisisons on tabs and fields
        Event::on(
            Fields::class,
            Fields::EVENT_AFTER_SAVE_FIELD_LAYOUT,
            function(FieldLayoutEvent $event) {
                $this->fabService->saveFieldLayoutPermissions($event->layout);
            }
        );
    }
}
