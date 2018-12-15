<?php

require_once('report.php');

$records = get_report();

?>



<head>
<style>
    body {background-color: powderblue;}
    h1   {color: blue;}
    span.field-name    {font-weight: bold;}
    span.field-value {padding-left: 4em;}
  </style>
</head>
<body>

  <div>Found <?php echo count($records); ?> records</div>
  <?php if (count($records) > 1): ?>
    <?php foreach ($records as $record): ?>
<div><span><a href="#<?php echo $record['Mss Number'];?>"><?php echo $record['Mss Number'];?></a></span></div>
  <?php endforeach;?>
  <?php endif; ?>

  <?php foreach ($records as $record): ?>
  <h1 id="<?php echo $record['Mss Number'];?>">Mss <?php echo $record['Mss Number'];?></h1>
  <?php foreach ($record as $field => $value): ?>
    <div class="field">
      <span class="field-name <?php echo $field; ?>"><?php echo $field; ?></span>
      <span class="field-value"><?php echo $value; ?></span>
    </div>
    <?php endforeach ?>
  <?php endforeach ?>

</body>



