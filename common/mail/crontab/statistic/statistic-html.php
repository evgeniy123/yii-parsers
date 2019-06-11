<?php

/* @var $counter int */
/* @var $time_execute_delete int */
/* @var $time_execute_move int */
/* @var $counter_inserted int */
/* @var $count_images_copied int */
/* @var $total int */


?>
<div class="password-reset" style="background-color: white; color: black">

    <p style="color: black">Total Images Inserted in Database: <?= $counter_inserted ?></p>
    <br>
    <p style="color: black">Total Images copied: <?= $total ?></p>
    <p style="color: black">Images moved: <?= $count_images_copied .  'Ne doveryat i ispravit s regulyarkami' ?></p>
    <p style="color: black">Time for move images: <?= $time_execute_move ?></p>
    <p style="color: black">Time for delete images: <?= $time_execute_delete ?></p>

</div>
