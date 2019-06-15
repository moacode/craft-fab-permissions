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

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\events\FieldLayoutEvent;
use craft\services\Fields;
use thejoshsmith\fabpermissions\assetbundles\fabpermissions\FabPermissionsAsset;

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
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Bootstrap this plugin
        $this->registerAssetBundles();
        $this->registerComponents();
        $this->handleEvents();

        Craft::info(
            Craft::t(
                'craft-fab-permissions',
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
        $request = Craft::$app->getRequest();
        if( $request->getIsCpRequest() ){
            FabPermissionsAsset::register(Craft::$app->view);
        }
    }

    /**
     * Registers Plugin Components
     * @author Josh Smith <me@joshsmith.dev>
     * @return void
     */
    protected function registerComponents()
    {
        // Override the Craft Fields service with our own one.
        // Note: I don't like overriding core components, but in this case it was the only way to tap into
        // the fields being returned by the service, and it seemed the "cleanest" approach.
        Craft::$app->setComponents([
            'fabService' => FabService::class,
            'fields' => [
                'class' => 'thejoshsmith\fabpermissions\services\Fields'
            ]
        ]);
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
