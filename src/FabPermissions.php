<?php
/**
 * Control Panel Permissions plugin for Craft CMS 3.x
 *
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

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * FabPermissions::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->registerComponents();
        $this->handleEvents();

        $request = Craft::$app->getRequest();
        if( $request->getIsCpRequest() ){
            FabPermissionsAsset::register(Craft::$app->view); // register asset bundle
        }

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

    protected function registerComponents()
    {
        $this->setComponents([
            'fabService' => FabService::class
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

        // Extend the CP template to override the tabs template with our modified version
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $e) {
                if (is_dir($baseDir = $this->getBasePath().DIRECTORY_SEPARATOR.'templates')) {
                    $e->roots[$this->id] = $baseDir;
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

        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_TEMPLATE,
            function(TemplateEvent $event) {

                // $view = Craft::$app->getView();
                // if( $view->getTemplateMode() === $view::TEMPLATE_MODE_CP ){
                //     $event->variables = $this->fabService->processCpEntriesVariables($event->variables);
                // }
// echo '<pre> $event->template: '; print_r($event->template); echo '</pre>'; die();
                // if( $event->template === "settings/sections/_entrytypes/edit" ){
                //     // $view->setTemplatesPath('');
                // }

                switch ($event->template) {
                    case '_includes/tabs':
                    case 'entries/_edit':
                        $event->variables = $this->fabService->processCpEntriesVariables($event->variables);
                        break;

                    default:
                        break;
                }
            }
        );
    }
}
