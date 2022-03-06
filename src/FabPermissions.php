<?php
/**
 * Field and Tab (Fab) Permissions plugin for Craft CMS 3.x
 * A plugin that allows admins to set tab and field restrictions for particular user groups in the system. For example, an admin could create a tabbed section that only they could see when creating entries.
 *
 * @link      https://joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace thejoshsmith\fabpermissions;

use Craft;
use craft\base\Plugin;
use craft\events\FieldLayoutEvent;
use craft\elements\Entry;
use craft\events\CreateFieldLayoutFormEvent;
use craft\models\FieldLayout;
use thejoshsmith\fabpermissions\assetbundles\fabpermissions\FabPermissionsAsset;
use thejoshsmith\fabpermissions\decorators\StaticFieldDecorator;
use thejoshsmith\fabpermissions\services\Fab as FabService;
use thejoshsmith\fabpermissions\services\Fields;

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
    public $schemaVersion = '1.0.3';

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Register services
        $this->registerComponents();

        // Ensure we only init the plugin on CP requests.
        if( !Craft::$app->getRequest()->getIsCpRequest() ) return false;

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
        
        if( $this->_shouldCheckPermissions() ) {

            Event::on(
                FieldLayout::class,
                FieldLayout::EVENT_CREATE_FORM,
                function(CreateFieldLayoutFormEvent $event) {

                    if (!$event->element instanceof Entry) {
                        return;
                    }

                    $layoutId = $event->sender->id;
                    $user = Craft::$app->getUser();
                    $fabService = FabPermissions::$plugin->fabService;

                    foreach($event->tabs as $k => $tab){

                        if( !$fabService->canViewTab($tab, $user) ){
                            unset($event->tabs[$k]);
                            continue;
                        }

                        foreach ($tab->elements as $i => $element) {

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
                }
            );
        }
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
