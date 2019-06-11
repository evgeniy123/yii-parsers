<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "shops".
 *
 * @property int $id
 * @property string $address
 * @property string $name
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Shoes[] $shoes
 */
class Shops extends ActiveRecord
{

    public function rules()
    {
        return [
            [['address', 'name'], 'string', 'max' => 100],
        ];
    }

    public static function tableName()
    {
        return '{{%shops}}';
    }


    public function attributeLabels()
    {
        return [
            'id' => Yii::t('users', 'ID'),
            'address' => Yii::t('users', 'Address'),
            'name' => Yii::t('users', 'Name'),
        ];
    }


    public function behaviors()
    {
        return [
            'class' => TimestampBehavior::className()
        ];
    }


}
