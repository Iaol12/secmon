<?php

use yii\db\Migration;

/**
 * Handles renaming of column `view_id` in table `{{%view_components}}`.
 */
class m251125_000100_rename_column_view_id_in_table_view_components extends Migration
{
    private $tableName = '{{%view_components}}';
    private $oldColumnName = 'view_id';
    private $newColumnName = 'dashboard_id'; // Assuming this is the logical new name

    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->renameColumn($this->tableName, $this->oldColumnName, $this->newColumnName);
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        // Revert: rename the new column back to the old one
        $this->renameColumn($this->tableName, $this->newColumnName, $this->oldColumnName);
    }
}