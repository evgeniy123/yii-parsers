<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "exceptions".
 *
 * @property int $id
 * @property string $name_shop
 * @property string $cause
 * @property int $created_at
 * @property int $code_error
 * @property integer $status
 * @property int $updated_at
 */
class Exceptions extends ActiveRecord
{
    const END_NOT_FOUND = 301;


    const ERROR = 1;
    const NOT_ERROR = 0;

    public static function tableName()
    {
        return '{{%exceptions}}';
    }

    public function rules()
    {
        return [
            [['cause', 'name_shop', 'code_error'], 'required'],
            ['code_error', 'integer'],
            ['name_shop', 'string', 'max' => '100'],
            ['cause', 'string', 'max' => '300'],
            ['status', 'in', 'range' => [self::ERROR, self::NOT_ERROR]],
        ];
    }


    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'status' => 'STATUS',
            'cause' => 'CAUSE',
            'name_shop' => 'NAME_SHOP',
            'updated_at' => 'Updated At',
            'created_at' => 'Created At'
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
