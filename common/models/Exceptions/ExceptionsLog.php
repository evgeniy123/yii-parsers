<?php

namespace console\helpers;

use common\models\Exceptions;

class ExceptionsLog
{
    private $queue;

    public function __construct()
    {
        $this->queue = new Exceptions();
    }

    public function insert($status, $shop_name, $cause, $code_error)
    {
        $this->queue->status = $status;
        $this->queue->name_shop = $shop_name;
        $this->queue->cause = $cause;
        $this->queue->code_error = $code_error;
        $this->queue->save(false);
    }
}