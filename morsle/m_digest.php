<?php
require_once('../../config.php');
require_once("$CFG->dirroot/repository/morsle/lib.php");
$sql = 'SELECT m.* from ' . $CFG->prefix . 'morsle_active m
        WHERE m.courseid = 6726';
//        JOIN ' . $CFG->prefix . 'course c on m.courseid = c.id
$todigest = $DB->get_record_sql($sql);
$shortname = $todigest->shortname;
$morsle = new repository_morsle();
$status = $morsle->morsle_digest($todigest);
?>
