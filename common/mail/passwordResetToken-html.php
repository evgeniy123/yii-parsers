<?php
use yii\helpers\Html;

/* @var $user \common\entities\User */
/* @var $this yii\web\View */
/* @var $user common\models\User */

// $resetLink = Yii::$app->urlManager->createAbsoluteUrl(['site/reset-password', 'token' => $user->password_reset_token]);

$confirmLink = Yii::$app->settings->getValue('url_site').'/auth/confirm-email?token='.$user->password_reset_token;

?>
<div class="password-reset" style="background-color: white; color: black">
    <p style="color: black">Hello <?= Html::encode($user->name) ?>,</p>

    <p style="color: black">Follow the link below to reset your password:</p>

    <p><?= Html::a(Html::encode($confirmLink), $confirmLink, ["color"=>"#ffa800"] ) ?></p>
</div>
