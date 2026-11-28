<?php
// Vela desenhada só com CSS. $typeId (cor vem da classe .ct-ID), $lit (acesa?), $size ('', 'sm', 'lg', 'xl')
$size = $size ?? '';
?>
<span class="candle ct-<?= (int) $typeId ?><?= $size !== '' ? ' candle-' . e($size) : '' ?><?= !empty($lit) ? '' : ' is-off' ?>" aria-hidden="true"><span class="flame"></span><span class="wick"></span><span class="wax"></span></span>
