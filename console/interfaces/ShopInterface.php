<?php

namespace console\interfaces;

interface ShopInterface
{
    const DIR_STATIC = '/var/www/archive/static';
    const PUBLIC_IMAGE_DIR = '/var/www/archive/public';
    //__ Kolichestvo timestamp katalogov. Esli budet bolshe -> email tak kak po idei do etogo yje doljno bilo udalitsya vse
    const MAX_NUMBER_PER_DIRECTORY = 1;
    const IMAGES_DIRECTORY = 'images';
    const PUBLIC_DIRECTORY = 'public';
    const CREATE_TABLES_DIRECTORY = '/var/www/archive/create_tables';

    //__ Katalog dlya xraneniya failov so spiskom tovarov dlya kajdogo magazina
    const LIST_SHOP_PRODUCTS = '/var/www/archive/lists_shop_products';

    const NEW = 1;
    const UPDATED = 0;
    const PROCESSING = 1;
    const PARSER_ON_ADMIN = 1;
    public function actionInsert();
}


