<?php

use yii\db\Migration;

class m210403_113834_019_create_table_dashboard_components extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%dashboard_components}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'chart_type' => $this->string(100)->notNull(),
            'timeframe' => $this->string(100),
            'config' => $this->jsonb(),
            'dashboard_id' => $this->integer()->notNull(),
            'filter_id' => $this->integer(),
        ], $tableOptions);

        $this->createIndex('idx_DC_dashboard', '{{%dashboard_components}}', 'dashboard_id');
        $this->createIndex('idx_DC_filter', '{{%dashboard_components}}', 'filter_id');
        $this->addForeignKey('fk_DC_dashboard', '{{%dashboard_components}}', 'dashboard_id', '{{%dashboards}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_DC_filter', '{{%dashboard_components}}', 'filter_id', '{{%filters}}', 'id', 'SET NULL', 'SET NULL');
    }

    public function down()
    {
        $this->dropTable('{{%dashboard_components}}');
    }
}
