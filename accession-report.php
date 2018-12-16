<?php

require_once('report.php');

$records = [];
$error = NULL;
$fresh = FALSE;

try {

  if (!empty($_POST)) {
    $search = $_POST['idsearch'];
    $records = search_records($search);
  }
  elseif (!empty($_GET['mss'])) {
    $search = $_GET['mss'];
    $records = get_record($search);
  }
  else {
    $fresh = TRUE;
  }
}
catch(Exception $e) {
  $error = $e;
}
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
  <h2>Search</h2>
  <div>Enter a string to search for accession records. Examples are "44", "4444", "4444-2002".</div>
  <form method="post">
    <input name="idsearch" type="text" />
  </form>

  <div class="error"><?php echo $error;?></div>
  <?php if (count($records) == 0 && !$fresh): ?>
  <div>No records found</div>
  <?php else :  ?>
    <?php if (count($records) > 1): ?>
      <div>Found <?php echo count($records); ?> records for search string '<?php echo $search;?>'</div>
      <?php foreach ($records as $record): ?>
      <div><span><a href="#<?php echo $record['Mss Number'];?>"><?php echo $record['Mss Number'];?></a></span></div>
      <?php endforeach;?>
    <?php endif; ?>

    <?php foreach ($records as $record): ?>
    <h1 id="<?php echo $record['Mss Number'];?>"><a href="<?php echo '/accession-report.php?mss=' . $record['Mss Number'];?>">Mss <?php echo $record['Mss Number'];?></a></h1>
    <?php foreach ($record as $field => $value): ?>
      <div class="field">
        <span class="field-name <?php echo $field; ?>"><?php echo $field; ?></span>
        <span class="field-value"><?php echo $value; ?></span>
      </div>
      <?php endforeach ?>
    <?php endforeach ?>
  <?php endif; ?>
</body>



