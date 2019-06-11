<?php

use common\entities\User;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $user User */

$confirmLink = Yii::$app->settings->getValue('url_site').'/auth/signup/confirm?token='.$user->email_confirm_token;
?>
<div class="password-reset" style="background-color: white; color: black">
    <p style="color: black">Hello <?= Html::encode($user->name) ?>,</p>

    <p style="color: black">Follow the link below to confirm your email:</p>

    <p><?= Html::a(Html::encode($confirmLink), $confirmLink, ["color"=>"#ffa800"] ) ?></p>
</div>
