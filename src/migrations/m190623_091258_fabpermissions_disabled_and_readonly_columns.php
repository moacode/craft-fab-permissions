<?php

namespace thejoshsmith\fabpermissions\migrations;

use Craft;
use craft\db\Migration;
use thejoshsmith\fabpermissions\records\FabPermissions as FabPermissionsRecord;

/**
 * m190623_091258_fabpermissions_disabled_and_readonly_columns migration.
 */
class m190623_091258_fabpermissions_disabled_and_readonly_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->renameColumn(FabPermissionsRecord::tableName(), 'permission', 'canView');
        $this->addColumn(FabPermissionsRecord::tableName(), 'canEdit', $this->boolean()->notNull()->after('canView'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(FabPermissionsRecord::tableName(), 'canEdit');
        $this->renameColumn(FabPermissionsRecord::tableName(), 'canView', 'permission');
    }
}
