<?php

// add birthday fields
function make_selector($to_number) {
  $options = array();
  $options[] = array(''=>0);
  for($i=1; $i<=$to_number; $i++) {
    $options[] = array($i, $i);
  }
  return $options;
}

function apply_strftime($data) {
  $newdata = array();
  foreach ($data as $item) {
    if ($item[1]==0) {
      $newdata[] = array(''=>0); continue;
    }
    $newdata[]= array(strftime("%B", mktime(0, 0, 0, $item[0]+1, 0, 0)), $item[1]);
  }
  return $newdata;
}

?>