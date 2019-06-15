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
use craft\events\TemplateEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;
use craft\services\Fields;
use thejoshsmith\fabpermissions\assetbundles\fabpermissions\FabPermissionsAsset;

use yii\base\Event;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    Josh Smith
 * @package   FabPermissions
 * @since     1.0.0
 *
 * @property  FabService $fabPermissionsService
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

    protected function registerAssetBundles()
    {
        // register anasset bundle on Control Panel requests
        $request = Craft::$app->getRequest();
        if( $request->getIsCpRequest() ){
            FabPermissionsAsset::register(Craft::$app->view);
        }
    }

    protected function registerComponents()
    {
        // $this->setComponents([

        // ]);
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

    protected function handleEvents()
    {
        // Do something after we're installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // We were just installed
                }
            }
        );

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
