<?php

namespace common\repositories\read;

use common\models\Shops;

class ShopsReadRepositories {
    /**
     * @param $category
     * @return Shops|null
     * @throws \Exception
     */
    public function getIdByCategory($category) {
        if (!$shop = Shops::find()->where(['name' => $category])->limit(1)->one())
            throw new \Exception('Net Takogo magazina po kategorii');

        return $shop['id'];
    }
}