<?php

use yii\db\Migration;

/**
 * Handles renaming of table `{{%view_components}}` to `{{%dashboard_components}}`.
 */
class m251125_000100_rename_table_view_components_to_dashboard_components extends Migration
{
    private $oldTableName = '{{%view_components}}';
    private $newTableName = '{{%dashboard_components}}';

    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->renameTable($this->oldTableName, $this->newTableName);
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        // Revert: rename the new table name back to the old one
        $this->renameTable($this->newTableName, $this->oldTableName);
    }
}