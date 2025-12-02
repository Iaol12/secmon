<?php

namespace app\models\Dashboard;

use Yii;
use app\models\Filter;
use app\models\Dashboard;

/**
 * This is the model class for table "dashboard_components".
 *
 * @property integer $id
 * @property integer $dashboard_id
 * @property integer $filter_id
 * @property string $config
 * @property integer $order
 * @property string $data_type
 * @property string $data_param
 *
 * @property Filter $filter
 * @property Dashboard $dashboard
 */
class Component extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'dashboard_components';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['dashboard_id'], 'required'],
            [['dashboard_id', 'filter_id', 'order'], 'integer'],
            [['config', 'data_type'], 'string'],
            [['data_param'], 'match', 'pattern' => '/^\d{1,5}[YMWDHmS]{1}$/', 'when' => function () {
                return $this->data_type == 'barChart';
            }, 'message' => 'Enter valid format(nY/nM/nW/nD/nH/nm/nS)!'],
            [['filter_id'], 'exist', 'skipOnError' => true, 'targetClass' => Filter::className(), 'targetAttribute' => ['filter_id' => 'id']],
            [['dashboard_id'], 'exist', 'skipOnError' => true, 'targetClass' => Dashboard::className(), 'targetAttribute' => ['dashboard_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'dashboard_id' => Yii::t('app', 'Dashboard ID'),
            'filter_id' => Yii::t('app', 'Filter ID'),
            'config' => Yii::t('app', 'Config'),
            'order' => Yii::t('app', 'Order'),
            'data_type' => Yii::t('app', 'Content Type'),
            'data_param' => Yii::t('app', 'Content Type Parameters')
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFilter()
    {
        return $this->hasOne(Filters::className(), ['id' => 'filter_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDashboard()
    {
        return $this->hasOne(Dashboard::className(), ['id' => 'dashboard_id']);
    }
}
