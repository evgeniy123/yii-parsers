<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "queue".
 *
 * @property int $id
 * @property string $name_shop
 * @property string $explication
 * @property int $created_at
 * @property integer $status
 * @property int $updated_at
 */
class Query extends ActiveRecord
{
    const PROCESSING = 1;
    const NOT_PROCESSING = 0;

    public static function tableName()
    {
        return '{{%queue}}';
    }

    public function rules()
    {
        return [
            [['explication', 'name_shop'], 'required'],
            ['explication', 'string', 'max' => '300'],
            ['name_shop', 'string', 'max' => '100'],
            ['status', 'in', 'range' => [self::PROCESSING, self::NOT_PROCESSING]],
        ];
    }


    public function attributeLabels()
    {
        return [
            'id' => Yii::t('users', 'ID'),
            'status' => Yii::t('users', 'STATUS'),
            'explication' => Yii::t('users', 'Explication'),
            'name_shop' => Yii::t('users', 'NAME_SHOP'),
            'updated_at' => Yii::t('users', 'Updated At'),
            'created_at' => Yii::t('users', 'Created At'),
        ];
    }


    /**
     * @return array
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

}
