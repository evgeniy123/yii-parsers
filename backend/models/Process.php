<?php

namespace backend\models;

use common\models\Shops;
use Yii;
use yii\db\Exception;

/**
 * This is the model class for table "process".
 *
 * @property integer $shop_id
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 */
class Process extends \yii\db\ActiveRecord
{
    const LIST_MAKE = 1;
    const PARSING_PRODUCT = 2;

    public static function tableName()
    {
        return '{{%process}}';
    }

    public function rules()
    {
        return [
            [['shop_id', 'status'], 'required'],
            ['status', 'in', 'range' => [self::LIST_MAKE, self::PARSING_PRODUCT]],
            ['shop_id', 'targetClass' => Shops::className(), 'message' => 'No this shop in DB. Validation failed !'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'shop_id' => Yii::t('users', 'Shop ID'),
            'status' => Yii::t('users', 'Status')
        ];
    }


    /**
     * @param $shop_id
     * @param $status
     * @param $scrabble_page
     * @return bool
     * @throws \yii\db\Exception
     */
    public static function updateActivity($shop_id, $status, $scrabble_page)
    {
        try{
            Yii::$app->db->createCommand('INSERT INTO  ' . self::tableName() . ' (shop_id, status, scrabble_page,  created_at)  VALUES (' . $shop_id . ', ' . $status . ', :scrabble_page,  ' . time() . ')  ON DUPLICATE KEY UPDATE updated_at = ' . time() . ',  created_at = created_at')->bindParam(':scrabble_page', $scrabble_page, \PDO::PARAM_STR)->execute();
        }
        catch (Exception $e){

           // echo $e->getMessage();
            //exit();
        }
      //  $sql = 'INSERT INTO  ' . self::tableName() . ' (shop_id, status, scrabble_page,  created_at)  VALUES (' . $shop_id . ', ' . $status . ', :scrabble_page,  ' . time() . ')  ON DUPLICATE KEY UPDATE updated_at = ' . time() . ',  created_at = created_at';

        return true;
    }
}
