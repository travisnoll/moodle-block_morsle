<?php
require_once('../../config.php');
require_once("$CFG->dirroot/google/lib.php");
$COURSE = $DB->get_record('course', array('id' => 6372));
/*
echo '<tr><td class="morslefiles"><a href="' . $CFG->wwwroot . '/blocks/morsle/morslefiles.php?courseid=' . $COURSE->id .
                '&wdir=/">
                <img src="' . $CFG->wwwroot . '/blocks/morsle/images/morslefiles.png" /></a></td>';
*/

?>
