<?php
/**
 * Fab Permissions plugin for Craft CMS 3.x
 *
 * @link      https://joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace thejoshsmith\fabpermissions\assetbundles\fabpermissions;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * FabPermissions AssetBundle
 *
 * @author    Josh Smith
 * @package   FabPermissions
 * @since     1.0.0
 */
class FabPermissionsAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * Initializes the bundle.
     */
    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = "@vendor/thejoshsmith/craft-fab-permissions/src/assetbundles/fabpermissions/dist";

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        // define the relative path to CSS/JS files that should be registered with the page
        // when this asset bundle is registered
        $this->js = [
            'js/FabPermissions.js',
        ];

        $this->css = [
            'css/FabPermissions.css',
        ];

        parent::init();
    }
}
