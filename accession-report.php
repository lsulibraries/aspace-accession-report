<?php

require_once('report.php');

$records = [];
$error = NULL;
$fresh = FALSE;

try {

  if (!empty($_POST)) {
    $search = trim_query_param($_POST['idsearch']);
    if (strlen($search) > 0) {
      $records = search_records($search);
    }
  }
  elseif (!empty($_GET['mss'])) {
    $search = trim_query_param($_GET['mss']);
    $records = get_record($search);
  }
  else {
    $fresh = TRUE;
  }
}
catch(Exception $e) {
    $error = "<strong>There was a problem with your search; see the following message for more information:</strong><br /> $e";
}
?>



<head>
<style>
    body {
        background-color: powderblue;
        padding-left: 3em;
        padding-right: 3em;
        font-family: sans-serif;
    }
    h1   {color: blue;}
    span.field-name    {
        font-weight: bold;
        width: 160px;
    }
    span.field-value {
        padding-left: 4em;
    }

    .search {

    }
    div.found {

    }

    dl {
      display: grid;
      grid-template-columns: 1fr 2fr;
    }

    dt {
      grid-column-start: 1;
      padding: 4px;
      font-variant: small-caps;
    }

    dd {
      grid-column-start: 2;
      padding: 4px;
    }

  </style>
  <style media="print">
    body: {
        font-family: Georgia, serif;
        font-size: 10pt;
        padding-top: 1em;
        padding-bottom: 1em;
    }

    a:link {
        text-decoration: none;
    }
        
    .search, .found {
      display: none;
    }
  </style>
</head>
<body>
  <div class="search">
    <form method="post" action="/accession-report.php">
      <label for="idsearch">Search</label>
      <input name="idsearch" type="text" placeholder='Ex: "44", "4444", "4444-2002"'/>
    </form>
  </div>
  <div class="error"><?php echo $error;?></div>
  <?php if (count($records) == 0 && !$fresh): ?>
  <div>No records found</div>
  <?php else :  ?>
    <?php if (count($records) > 1): ?>
      <div class="found"><span>Found <?php echo count($records); ?> records for search string '<?php echo $search;?>'</span>
        <?php foreach ($records as $record): ?>
        <div><span><a href="#<?php echo $record['Mss Number'];?>"><?php echo $record['Mss Number'];?></a></span></div>
        <?php endforeach;?>
      </div>
    <?php endif; ?>

    <?php foreach ($records as $record): ?>
    <h1 id="<?php echo $record['Mss Number'];?>"><a href="<?php echo '/accession-report.php?mss=' . $record['Mss Number'];?>"><?php echo $record['Mss Number'];?></a></h1>
    <dl>
    <?php foreach ($record as $field => $value): ?>
      <dt class="field-name <?php echo $field; ?>"><?php echo $field; ?></dt>
<dd class="field-value"><?php echo strlen($value) > 0 ? $value : '-'; ?></dd>
      <?php endforeach ?>
    </dl>
    <?php endforeach ?>
  <?php endif; ?>
</body>



