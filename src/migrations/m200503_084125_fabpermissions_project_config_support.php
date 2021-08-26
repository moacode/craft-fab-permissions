<?php

namespace thejoshsmith\fabpermissions\migrations;

use thejoshsmith\fabpermissions\records\FabPermissionsRecord;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;

/**
 * m200503_084125_fabpermissions_project_config_support migration.
 */
class m200503_084125_fabpermissions_project_config_support extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Drop all foreign keys
        MigrationHelper::dropAllForeignKeysOnTable(FabPermissionsRecord::tableName());

        // Rename tabId to tabName and update type
        $this->renameColumn(FabPermissionsRecord::tableName(), 'tabId', 'tabName');
        $this->alterColumn(FabPermissionsRecord::tableName(), 'tabName', $this->string());

        $tabs = (new Query())
            ->select(['id', 'name'])
            ->from('{{%fieldlayouttabs}}')
        ->all();

        if( !empty($tabs) ){
            foreach ($tabs as $tab) {
                Craft::$app->db->createCommand()
                    ->update(FabPermissionsRecord::tableName(), ['tabName' => $tab['name']], ['tabName' => $tab['id']])
                ->execute();
            }
        }

        // Rebuild the project config to add permissions currently stored in the DB
        Craft::$app->getProjectConfig()->rebuild();
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->addForeignKeys();
        $this->renameColumn(FabPermissionsRecord::tableName(), 'tabName', 'tabId');
        $this->alterColumn(FabPermissionsRecord::tableName(), 'tabId', $this->int());
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey(
            $this->db->getForeignKeyName(FabPermissionsRecord::tableName(), 'siteId'),
            FabPermissionsRecord::tableName(),
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName(FabPermissionsRecord::tableName(), 'tabId'),
            FabPermissionsRecord::tableName(),
            'tabId',
            '{{%fieldlayouttabs}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

         $this->addForeignKey(
            $this->db->getForeignKeyName(FabPermissionsRecord::tableName(), 'fieldId'),
            FabPermissionsRecord::tableName(),
            'fieldId',
            '{{%fieldlayoutfields}}',
            'fieldId',
            'CASCADE',
            'CASCADE'
        );

         $this->addForeignKey(
            $this->db->getForeignKeyName(FabPermissionsRecord::tableName(), 'layoutId'),
            FabPermissionsRecord::tableName(),
            'layoutId',
            '{{%fieldlayouts}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

         $this->addForeignKey(
            $this->db->getForeignKeyName(FabPermissionsRecord::tableName(), 'userGroupId'),
            FabPermissionsRecord::tableName(),
            'userGroupId',
            '{{%usergroups}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

}
