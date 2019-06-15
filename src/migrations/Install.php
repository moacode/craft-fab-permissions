<?php

namespace thejoshsmith\fabpermissions\migrations;

use thejoshsmith\fabpermissions\FabPermissions;
use thejoshsmith\fabpermissions\records\FabPermissions as FabPermissionsRecord;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

/**
 * Fab Permissions Install Migration
 *
 * If your plugin needs to create any custom database tables when it gets installed,
 * create a migrations/ folder within your plugin folder, and save an Install.php file
 * within it using the following template:
 *
 * If you need to perform any additional actions on install/uninstall, override the
 * safeUp() and safeDown() methods.
 *
 * @author    Josh Smith <me@joshsmith.dev>
 * @package   FabPermissions
 * @since     1.0.0
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables needed for the Records used by the plugin
     *
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema(FabPermissionsRecord::tableName());
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                FabPermissionsRecord::tableName(),
                [
                    'id' => $this->primaryKey(),
                    'layoutId' => $this->integer()->notNull(),
                    'tabId' => $this->integer(),
                    'fieldId' => $this->integer(),
                    'siteId' => $this->integer()->notNull(),
                    'userGroupId' => $this->integer()->notNull(),
                    'permission' => $this->boolean()->notNull(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * Creates the indexes needed for the Records used by the plugin
     *
     * @return void
     */
    protected function createIndexes()
    {
        // Additional commands depending on the db driver
        switch ($this->driver) {
            case DbConfig::DRIVER_MYSQL:
                break;
            case DbConfig::DRIVER_PGSQL:
                break;
        }
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

    /**
     * Populates the DB with the default data.
     *
     * @return void
     */
    protected function insertDefaultData()
    {
    }

    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists(FabPermissionsRecord::tableName());
    }
}
