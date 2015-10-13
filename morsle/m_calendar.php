<?php
require_once realpath(dirname(__FILE__) . '/../../config.php');
require_once("$CFG->dirroot/repository/morsle/lib.php");
$morsle = new repository_morsle(1);
//$status = $morsle->m_calendar();
$course = strtolower("'60301-ACCTG-250-A'");
// determine rosters for everything else based on visibility of course, removing students if not visible
$status = $morsle->m_maintain($course);

?>