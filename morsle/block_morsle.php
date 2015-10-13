<?php
global $CFG;
require_once($CFG->dirroot.'/config.php');

class block_morsle extends block_base {

    public function init() {
        $this->title = get_string('morsle', 'block_morsle');
    }

  //user can only add only one instance of morsle per course
   function instance_allow_multiple() {
      return false;
    }

   //enable admin custom settings
    function has_config() {
      return true;
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
    global $COURSE, $CFG, $USER, $DB, $OUTPUT;
    $this->content = new stdClass;
    $morslerec = new stdClass();
    $mhelp = get_string('morsle_help_string', 'block_morsle');

    // expiration of block
    $MORSLE_EXPIRES = is_null(get_config('morsle','morsle_expiration')) ? 30 * 24 * 60 * 60: get_config('morsle','morsle_expiration') * 24 * 60 * 60;
    $curtime = time();
    if (($COURSE->startdate + $MORSLE_EXPIRES) < $curtime || $COURSE->startdate == 0) {
        $this->content = null;
        return $this->content;
    }
    $context = context_course::instance($COURSE->id);
    $conditions = array('courseid' => $COURSE->id);

    // create morslerec if needed EVEN IF course is invisible to students
    // only if user has editingteacher role and not admin
    // admin entering unused course will not create the morsle record
    if (!$morslerec = $DB->get_record('morsle_active',$conditions)) {
        if(has_capability('moodle/course:update', $context) && !is_siteadmin($USER)) {
             $newrec->created = time();
             $newrec->status = 'Full';
             $newrec->courseid = $COURSE->id;
             $newrec->shortname = strtolower($COURSE->shortname);
             if ($morslerec = $DB->insert_record('morsle_active', $newrec)) {
                 add_to_log($COURSE->id, 'morsle', "morsle FULL record created for $COURSE->shortname");
              } else {
                  add_to_log($COURSE->id, 'morsle', "morsle FULL record NOT CREATED for $COURSE->shortname");
              }
        }else {
              $morslerec = new stdClass();
        }
   }
   $username = $COURSE->shortname . '@luther.edu';
   $urlshortname = str_replace('@', '%40', strtolower($username));
   $returnurl = curPageURL2();

   $this->content->text = '';
   $this->content->text .= '<table class="morslefull" style="width: 100%;">';
   $imsrc = $CFG->wwwroot . '/blocks/morsle/images/morslelogobackground.png';
 //  $this->content->text .= '<tr><td class="morslelogo morsletop" colspan = "2" style="background-image:url('.$imsrc.');background-repeat:no-repeat;background-size:100%;background-position:center;">';
   $this->content->text .= '<tr><td class="morslelogo morsletop" colspan = "2"><img class="logo" src="' . $imsrc . '" alt=\"Norse Docs for Moodle" />';
   $this->content->text .= '</td></tr>';

   if (!isset($morslerec)) {
         $coursecalendar = '&nbsp&nbspNo Calendar Information Available<br/>';
	$this->content->text .= '<tr><td colspan = "2" class="calendar">' . $coursecalendar;

   }elseif(!isset($morslerec->password)){
	$coursecalendar = '&nbsp&nbspMorsle Calendar Not Yet Available<br/>';
	$this->content->text .= '<tr><td colspan = "2" class="calendar">' . $coursecalendar;

   }else { //morsle block is available
	$coursecalendar = '<object data="https://www.google.com/calendar/b/0/embed?showTitle=0&amp;showNav=0&amp;showDate=0&amp;showPrint=0&amp;showTabs=0&amp;showCalendars=0&amp;showTz=0&amp;mode=AGENDA&amp;height=120&amp;wkst=1&amp;bgcolor=%23e3e9ff&amp;src='
                . $urlshortname 
                . '&amp;color=%23856508&amp;ctz=America%2FChicago" type="text/html" id="embeddedhtml" style ="border-radius: none;"  width="255" height="200"></object>';
	$coursecalendar .="</div></div>";
	$coursecalendar .="<div id='usernotloggedin' style='padding-top:5px;padding-left:5px;display:none;'><p><a href='https://accounts.google.com/ServiceLogin?hl=en&continue=https://www.google.com/#identifier'>Log into Google</a> to use Morsle.</p></div>";
	$this->content->text .= '<tr><td colspan = "2" class="calendar">' . $coursecalendar;
   }
   $this->content->text .= '</td></tr>';

   if (!isset($morslerec->password)) {
        $this->content->text .= '<tr><td colspan = "2">';
        $this->content->text .= 'Norse Apps resources for this course not yet available</td></tr></table>';
   } else {
	$this->content->text .= '<tr><td class="morsleleft"><a target="_blank" href="mailto:' 
                . $morslerec->shortname . '-group@luther.edu"><img src="' . $CFG->wwwroot 
                . '/blocks/morsle/images/mailAllCourseMembersCell.png" /></a></td>';

        $this->content->text .= '<td class="morsleright"><a href="' 
                . $CFG->wwwroot . '/blocks/morsle/lang/help/morsle/morsle.html" target="_blank"><img src="' 
                . $CFG->wwwroot . '/blocks/morsle/images/helpWithMorsleCell.png" /></a></td></tr>';

        $this->content->text .= '</table>';
        }
        $this->content->footer = '';
    return $this->content;
  }
    function specialization() {
      //empty!
    } //specialization

}
function curPageURL2() {
        $pageURL = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") {
                $pageURL .= "s";
        }
        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
                $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
        } else {
                $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        }
        return $pageURL;
}
