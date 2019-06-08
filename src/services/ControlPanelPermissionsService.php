<?php
/**
 * Control Panel Permissions plugin for Craft CMS 3.x
 *
 * A plugin that allows admins to set tab and field restrictions for particular user groups in the system. For example, an admin could create a tabbed section that only they could see when creating entries.
 *
 * @link      https://joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace thejoshsmith\controlpanelpermissions\services;

use thejoshsmith\controlpanelpermissions\ControlPanelPermissions;

use Craft;
use craft\base\Component;

/**
 * ControlPanelPermissionsService Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Josh Smith
 * @package   ControlPanelPermissions
 * @since     1.0.0
 */
class ControlPanelPermissionsService extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * This function can literally be anything you want, and you can have as many service
     * functions as you want
     *
     * From any other plugin file, call it like this:
     *
     *     ControlPanelPermissions::$plugin->controlPanelPermissionsService->exampleService()
     *
     * @return mixed
     */
    public function exampleService()
    {
        $result = 'something';

        return $result;
    }
}
