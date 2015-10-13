<?php

require_once('../../config.php'); // comment out when running in cron
require_once($CFG->dirroot.'/google/lib.php');
require_once($CFG->dirroot.'/google/gauth.php');
require_once($CFG->dirroot.'/lib/accesslib.php');

//$authstring = "Authorization: GoogleLogin auth=" . clientauth();

/*
 * Library parts primarily used by morsle_files
 */


/*
 * @param string wdir - google directory with which we're dealing
 * @param string collectionid - passed by reference which holds the id for the collection to be dealt with
 * @param string owner - passed by reference which holds the account name under which any google actions will take place
 */
function morsle_get_files($morsle, $wdir, &$collectionid) {
	global $USER, $COURSE;
	global $userstr, $deptstr;
        $collections = explode('/',$wdir);
	if ($wdir === '') { // root of morsle files, user and department are prepended in display_dir
	    $files = get_doc_feed($morsle, $collectionid); // go get folder contents from Google
	
            
        } elseif (strpos($wdir, $deptstr) === false && strpos($wdir, $userstr) === false) { // course collection
            $basecollectionid = sizeof($collections) > 2 ? get_collection($morsle, $collections[sizeof($collections)-2]) : null; // $basecollectionid = second to last collection in path that is passed
	    $collectionid = get_collection($morsle, $collections[sizeof($collections)-1], $basecollectionid); // $collectionid = last collection in path that is passed
            // TODO: send a path to be used to get the doc feed from a nested collection
	    $files = get_doc_feed($morsle, $collectionid); // go get folder contents from Google
	
            
        } else { // departmental or user account collection
            $basecollectionid = sizeof($collections) > 3 ? get_collection($morsle, $collections[sizeof($collections)-2]) : null; // $basecollectionid = second to last collection in path that is passed
	    $collectionid = sizeof($collections) > 2 ? get_collection($morsle, $collections[sizeof($collections)-1], $basecollectionid) : null; // $collectionid = last collection in path that is passed
		// go get folder contents from Google
	    if ($collectionid == null || $collectionid === '') {
                $collectionid = 'root';
            }
            // TODO: send a path to be used to get the doc feed from a nested collection
	    $files = get_doc_feed($morsle, $collectionid); // go get folder contents from Google
	}
	return $files;
}

function link_to_gdoc($name, $link, $type = null, $modtype = 'url') {
    global $COURSE, $DB, $CFG, $USER;
    require_once("$CFG->dirroot/mod/$modtype/lib.php");

    //add
    $fromform = new stdClass();
    $newform = new stdClass();
    $mform = new MoodleQuickForm(null, 'POST', 'nothing');
    $module 					= $DB->get_record("modules", array('name' => $modtype));
    $course 					= $COURSE;
    $cw 						= get_course_section(0, $course->id);
    $cm 						= null;

    // fields for mdl_url
    $fromform->course           = $course->id;
    $fromform->name 			= $name;
    $fromform->introformat 		= 0;
    $fromform->introeditor 		= 0;
    $fromform->externalurl      = $link;
/*    if ($type !== 'dir') {
        $fromform->display          = 6;
        $fromform->displayoptions = 'a:2:{s:10:"popupwidth";i:1024;s:11:"popupheight";i:768;}';
    } else {
*/        $fromform->display          = 0;
        $fromform->popupwidth		= 1024;
        $fromform->popupheight		= 768;
        $fromform->popupwidth		= null;
        $fromform->popupheight		= null;
        $fromform->displayoptions = 'a:1:{s:10:"printintro";i:0;}';
//    }

    // fields for mdl_course_module
    $fromform->module           = $module->id;
    $fromform->instance 		= '';
    $fromform->section          = 0;  // The section number itself - relative!!! (section column in course_sections)
    $fromform->idnumber 		= null;
    $fromform->score	 		= 0;
    $fromform->indent	 		= 0;
    $fromform->visible	 		= 1;
    $fromform->visibleold 		= 1;
    $fromform->groupmode        = $course->groupmode;
    $fromform->groupingid 		= 0;
    $fromform->groupmembersonly = 0;
    $fromform->completion 		= 0;
    $fromform->completionview	= 0;
    $fromform->completionexpected	= 0;
    $fromform->availablefrom	= 0;
    $fromform->availableuntil	= 0;
    $fromform->showavailability	= 0;
    $fromform->showdescription	= 0;

    $fromform->conditiongradegroup	 		= array();
    $fromform->conditionfieldgroup	 		= array();

    // fields for mdl_course_sections
    $fromform->summaryformat	= 0;


    $fromform->modulename 		= clean_param($module->name, PARAM_SAFEDIR);  // For safety
    //	$fromform->add              = 'resource';
//	$fromform->type             = $type == 'dir' ? 'collection' : 'file';
//	$fromform->return           = 0; //must be false if this is an add, go back to course view on cancel
//    $fromform->coursemodule 	= '';
//	$fromform->popup			= 'resizable=1,scrollbars=1,directories=1,location=1,menubar=1,toolbar=1,status=1,width=1024,height=768';

//	require_login($course->id); // needed to setup proper $COURSE

    $context = context_course::instance($COURSE->id);
    require_capability('moodle/course:manageactivities', $context);

    if (!empty($course->groupmodeforce) or !isset($fromform->groupmode)) {
            $fromform->groupmode = 0; // do not set groupmode
    }

    if (!course_allowed_module($course, $fromform->modulename)) {
            print_error('moduledisable', '', '', $fromform->modulename);
    }

    // first add course_module record because we need the context
    $newcm = new stdClass();
    $newcm->course           = $course->id;
    $newcm->module           = $fromform->module;
    $newcm->instance         = 0; // not known yet, will be updated later (this is similar to restore code)
    $newcm->visible          = $fromform->visible;
    $newcm->groupmode        = $fromform->groupmode;
    $newcm->groupingid       = $fromform->groupingid;
    $newcm->groupmembersonly = $fromform->groupmembersonly;
    $completion = new completion_info($course);
    if ($completion->is_enabled()) {
            $newcm->completion                = $fromform->completion;
            $newcm->completiongradeitemnumber = $fromform->completiongradeitemnumber;
            $newcm->completionview            = $fromform->completionview;
            $newcm->completionexpected        = $fromform->completionexpected;
    }
    if(!empty($CFG->enableavailability)) {
            $newcm->availablefrom             = $fromform->availablefrom;
            $newcm->availableuntil            = $fromform->availableuntil;
            $newcm->showavailability          = $fromform->showavailability;
    }
    if (isset($fromform->showdescription)) {
            $newcm->showdescription = $fromform->showdescription;
    } else {
            $newcm->showdescription = 0;
    }

    if (!$fromform->coursemodule = add_course_module($newcm)) {
            print_error('cannotaddcoursemodule');
    }

    if (plugin_supports('mod', $fromform->modulename, FEATURE_MOD_INTRO, true)) {
            $draftid_editor = file_get_submitted_draft_itemid('introeditor');
            file_prepare_draft_area($draftid_editor, null, null, null, null);
            $fromform->introeditor = array('text'=>'', 'format'=>FORMAT_HTML, 'itemid'=>$draftid_editor); // TODO: add better default
    }

    if (plugin_supports('mod', $fromform->modulename, FEATURE_MOD_INTRO, true)) {
            $introeditor = $fromform->introeditor;
            unset($fromform->introeditor);
            $fromform->intro       = $introeditor['text'];
            $fromform->introformat = $introeditor['format'];
    }


    $addinstancefunction    = $fromform->modulename."_add_instance";
    $updateinstancefunction = $fromform->modulename."_update_instance";

    $returnfromfunc = $addinstancefunction($fromform, $mform);

    //	$returnfromfunc = url_add_instance($fromform, $mform);
    if (!$returnfromfunc or !is_number($returnfromfunc)) {
        // undo everything we can
        $modcontext = context_module::instance($fromform->coursemodule);
        delete_context(CONTEXT_MODULE, $fromform->coursemodule);
        $DB->delete_records('course_modules', array('id'=>$fromform->coursemodule));

        if (!is_number($returnfromfunc)) {
            print_error('invalidfunction', '', course_get_url($course, $cw->section));
        } else {
            print_error('cannotaddnewmodule', '', course_get_url($course, $cw->section), $fromform->modulename);
        }
    }

        $fromform->instance = $returnfromfunc;

    $DB->set_field('course_modules', 'instance', $returnfromfunc, array('id'=>$fromform->coursemodule));


    // update embedded links and save files
    $modcontext = context_module::instance($fromform->coursemodule);
    if (!empty($introeditor)) {
        $fromform->intro = file_save_draft_area_files($introeditor['itemid'], $modcontext->id,
                                                      'mod_'.$fromform->modulename, 'intro', 0,
                                                      array('subdirs'=>true), $introeditor['text']);
        $DB->set_field($fromform->modulename, 'intro', $fromform->intro, array('id'=>$fromform->instance));
    }

    // course_modules and course_sections each contain a reference
    // to each other, so we have to update one of them twice.
    $sectionid = add_mod_to_section($fromform);

    $DB->set_field('course_modules', 'section', $sectionid, array('id'=>$fromform->coursemodule));

        // make sure visibility is set correctly (in particular in calendar)
        set_coursemodule_visible($fromform->coursemodule, $fromform->visible);

        if (isset($fromform->cmidnumber)) { //label
                // set cm idnumber
                set_coursemodule_idnumber($fromform->coursemodule, $fromform->cmidnumber);
        }


    // Set up conditions
    if ($CFG->enableavailability) {
        condition_info::update_cm_from_form((object)array('id'=>$fromform->coursemodule), $fromform, false);
    }

    $eventname = 'mod_created';

        add_to_log($course->id, "course", "add mod",
                           "../mod/$fromform->modulename/view.php?id=$fromform->coursemodule",
                           "$fromform->modulename $fromform->instance");
        add_to_log($course->id, $fromform->modulename, "add",
                           "view.php?id=$fromform->coursemodule",
                           "$fromform->instance", $fromform->coursemodule);
    // Trigger mod_created/mod_updated event with information about this module.
    $eventdata = new stdClass();
    $eventdata->modulename = $fromform->modulename;
    $eventdata->name       = $fromform->name;
    $eventdata->cmid       = $fromform->coursemodule;
    $eventdata->courseid   = $course->id;
    $eventdata->userid     = $USER->id;
    events_trigger($eventname, $eventdata);

    rebuild_course_cache($course->id);

    return 1;
}

function setfilelist($VARS, $wdir, $owner, $files, $type) {
    global $CFG, $USER, $COURSE;

    $USER->filelist = array();
    $USER->fileop = "";
    $courseid = $COURSE->id;
//	$collection = get_collection($wdir, $owner);
    if ($VARS['file1'] || $VARS['dir1']) {
//    foreach ($VARS as $key => $val) {
        $val = $VARS['file1'];
    	// pick off the passed parameters that are file params
    	if ($type == "file") {
            $USER->filelist[$val] = new stdClass();
            // look for $val in title of files array members
            foreach ($files as $name => $file) {
                if ($name == $val) {
                    $USER->filelist[$val]->id = $file->id;
                    $USER->filelist[$val]->link = substr($file->alternateLink,0,-18);
                    $USER->filelist[$val]->type = 'file';
                    break;
                }
            }
        // pick off the passed parameters that are directory params
    	// TODO: don't know what we only deal with directory types here and both types up above?
    	} elseif ($type == "dir") {
//            $val = $VARS['dir1'];
            // pick off the passed parameters that are file params
            $USER->filelist[$val] = new stdClass();
            foreach ($files as $name => $file) {
                if ($name == $val) {
                    $USER->filelist[$val]->id = $file->id;
                    $USER->filelist[$val]->link = "$CFG->wwwroot/blocks/morsle/morslefiles.php?courseid=$courseid&wdir=$name&id=$COURSE->id&file=$name&type=dir";
                    $USER->filelist[$val]->type = 'dir';
                    break;
                }
            }
        }
    }
    return;
}

function displaydir ($wdir, $files) {
    //  $wdir == / or /a or /a/b/c/d  etc

    @ini_set('memory_limit', '1024M');
    global $courseid, $DB, $OUTPUT;
    global $USER, $CFG, $COURSE;
    global $choose;
    global $deptstr, $userstr;
    require_once($CFG->dirroot . '/blocks/morsle/constants.php');

    $course = $COURSE;
    $user = $USER;


	// Get the sort parameter if there is one
    $sort = optional_param('sort', 1, PARAM_INT);
    $dirlist = array();
    $filelist = array();
    $dirhref = array();
    $filehref = array();
    $courseid = $course->id;
    $coursecontext = context_course::instance($COURSE->id);


    // separate all the files list into directories and files
    foreach ($files as $name=>$file) {
        if (is_folder($file)) {
            $dirlist[$name] = $file;
        } else {
            $filelist[$name] = $file;
        }
    }

    // setup variables and strings
    $strname = get_string("name", 'block_morsle');
    $strsize = get_string("size");
    $strmodified = get_string("modified");
    $straction = get_string("action");
    $strmakeafolder = get_string("morslemakecollection", 'block_morsle');
    $struploadafile = get_string("uploadafile");
    $strselectall = get_string("selectall");
    $strselectnone = get_string("deselectall");
    $strwithchosenfiles = get_string("withchosenfiles");
    $strmovetoanotherfolder = get_string("movetoanotherfolder");
    $strlinktocourse = get_string("linktocourse", 'block_morsle');
    $strmovefilestohere = get_string("movefilestohere");
    $strdeletefromcollection = get_string("deletefromcollection",'block_morsle');
    $strcreateziparchive = get_string("createziparchive");
    $strrename = get_string("rename");
    $stredit   = get_string("edit");
    $strunzip  = get_string("unzip");
    $strlist   = get_string("list");
    $strrestore= get_string("restore");
    $strchoose = get_string("choose");
    $strfolder = get_string("folder");
    $strfile   = get_string("file");
    $strdownload = get_string("strdownload", 'block_morsle');
    $struploadthisfile = get_string("uploadthisfile");
    $struploadandlinkthisfile = get_string("uploadandlinkthisfile", 'block_morsle');

    $filesize = 'Varies as to type of document';
    $strmaxsize = get_string("maxsize", "", $filesize);
    $strcancel = get_string("cancel");
    $strmodified = get_string("strmodified", 'block_morsle');

    //CLAMP #289 set color and background-color to transparent
	//Kevin Wiliarty 2011-03-08
    $padrename = get_string("rename");

    $padedit = $padunzip = $padlist = $padrestore = $padchoose = $padfolder = $padfile = $padlink = '';
    $attsArr = array($padedit=>$stredit, $padunzip=>$strunzip, $padlist=>$strlist, $padrestore=>$strrestore, $padchoose=>$strchoose, $padfolder=>$strfolder, $padfile=>$strfile, $padlink=>$strlinktocourse);
               foreach ($attsArr as $key => $value) {
                    $key = html_writer::div($value . '&nbsp', '', array('style'=>'color:transparent; background-color:transparent; display:inline;'));
                }
/*
    $padedit = html_writer::div($stredit . '&nbsp','', array('style'=>'color:transparent; background-color:transparent; display:inline'));
    $padunzip = html_writer::div($strunzip . '&nbsp','', array('style'=>'color:transparent; background-color:transparent; display:inline'));
    $padlist = html_writer::div($strlist . '&nbsp','', array('style'=>'color:transparent; background-color:transparent; display:inline'));
    $padrestore = html_writer::div($strrestore . '&nbsp','', array('style'=>'color:transparent; background-color:transparent; display:inline'));
    $padchoose = html_writer::div($strchoose . '&nbsp','', array('style'=>'color: transparent; background-color:transparent; display:inline'));
    $padfolder = html_writer::div($strfolder . '&nbsp','', array('style'=>'color:transparent; background-color:transparent; display:inline;'));
    $padfile = html_writer::div($strfile . '&nbsp','', array('style'=>'color:transparent; background-color; transparent; display:inline;'));
    $padlink = html_writer::div($strlinktocourse . '&nbsp','', array('style'=>'color:transparent; background-color:transparent; display:inline;'));
 */ 
    $gdocsstr = 'Google-Docs-Storage-for-';

    // Set sort arguments so that clicking on a column that is already sorted reverses the sort order
    $sortvalues = array(1,2,3);
    foreach ($sortvalues as &$sortvalue) {
	    if ($sortvalue == $sort) {
            $sortvalue = -$sortvalue;
        }
    }

    $upload_max_filesize = get_max_upload_file_size($CFG->maxbytes);

    // beginning of with selected files portion
    echo html_writer::start_tag('table', array('border'=>'0','cellspacing'=>'2','cellpadding'=>'2','style'=>'min-width: 900px; margin-left:auto; margin-right:auto','class'=>'files'));
    if ($wdir !== '') {
        echo html_writer::start_tag('tr');

        //html_writer::table($table);
        if (!empty($USER->fileop) and ($USER->fileop == "move") and ($USER->filesource <> $wdir)) {
            echo html_writer::start_tag('td', array('colspan'=>'3','align'=>'center'));
            // move files to other folder form
            echo html_writer::start_tag('form', array('action'=>'morslefiles.php','method'=>'get'));
            echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'choose','value'=>$choose));
            echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'courseid','value'=>$courseid));
            echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'wdir','value'=>$wdir));
            echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'action','value'=>'paste'));
            echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'sesskey','value'=>$USER->sesskey));
            echo html_writer::tag('input', '', array('align'=>'center','type'=>'submit','value'=>$strmovefilestohere));
            //echo "<span> --> <b>$wdir</b></span><br />";
            echo html_writer::start_span() . '-->' . html_writer::tag('b', $wdir) . html_writer::end_span() . html_writer::end_tag('br');
            echo html_writer::end_tag('td');
            echo html_writer::start_tag('td');
            echo html_writer::end_tag('form');

            // cancel moving form
            echo html_writer::start_tag('form', array('action'=>'morslefiles.php','method'=>'get','align'=>'left'));
            echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'choose','value'=>$choose));
            echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'courseid','value'=>$courseid));
            echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'wdir','value'=>$wdir));
            echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'action','value'=>'cancel'));
            echo html_writer::tag('input', '', array('type'=>'submit','value'=>$strcancel,'style'=>'color:red; margin-left:10px'));
            echo html_writer::end_tag('form');
            echo html_writer::end_tag('td');
        } else {
            if (has_capability('moodle/course:update', $coursecontext) || strpos($wdir,'-write')) {
                echo html_writer::start_tag('tr', array('style'=>'background-color: #ffddbb;'));
                echo html_writer::start_tag('td', array('colspan'=>'3','align'=>'left','style'=>'background-color:#ffddbb; padding-left:5px;'));


                // file upload form
                // TODO: what if we're in the user or departmental dir?
                echo html_writer::start_tag('form', array('enctype'=>'multipart/form-data','method'=>'post','action'=>'morslefiles.php'));
                echo html_writer::start_span() . '&nbsp' . $struploadafile .'&nbsp('.$strmaxsize.')&nbsp'. html_writer::tag('b', $wdir) . html_writer::end_span() . html_writer::tag('br','');
                echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'choose','value'=>$choose));
                echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'courseid','value'=>$courseid));
                echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'wdir','value'=>$wdir));
                echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'action','value'=>'upload'));
                echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'sesskey','value'=>$USER->sesskey));
                if (!isset($coursebytes)) { 
                    $coursebytes = 0; 
                }
                if (!isset($modbytes)) { 
                    $modbytes = 0; 
                }
                $maxbytes = get_max_upload_file_size($CFG->maxbytes, $coursebytes, $modbytes);
                $str = html_writer::tag('input', '', array('type'=>'hidden','name'=>'MAX_FILE_SIZE','value'=>$maxbytes)) . "\n";
                $name = 'userfile';
                $str .= html_writer::tag('input', '', array('type'=>'file','size'=>'50','name'=>$name,'alt'=>$name, 'style'=>'margin-left: 5px;')) . html_writer::end_tag('br') . "\n";

                echo $str;
                echo html_writer::tag('input', '', array('type'=>'submit','name'=>'save','value'=>$struploadthisfile,'style'=>'color:green; padding-left:5px;'));
                echo html_writer::tag('input', '', array('type'=>'submit','name'=>'savelink','value'=>$struploadandlinkthisfile,'style'=>'color:blue; padding-left:5px;'));
                echo html_writer::end_tag('form');
                echo html_writer::end_tag('td');
                echo html_writer::end_tag('tr');

                // cancel button div only if not in root morsle directory
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td','',array('colspan'=>'2','style'=>'background-color:#ffddbb;'));
                echo html_writer::start_tag('td', array('style'=>'background-color:#ffddbb; padding-left:5px;','colspan'=>'1','align'=>'right'));
                echo html_writer::start_tag('form', array('action'=>'morslefiles.php','method'=>'get','align'=>'left'));
                echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'choose','value'=>$choose));
                echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'courseid','value'=>$courseid));
                echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'wdir','value'=>$wdir));
                echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'action','value'=>'cancel'));
                echo html_writer::tag('input', '', array('type'=>'submit','value'=>$strcancel,'align'=>'left','style'=>'color:red;'));
                echo html_writer::end_tag('form');
                echo html_writer::end_tag('td');
                echo html_writer::end_tag('tr');
                echo html_writer::end_tag('tr');
                echo html_writer::start_tag('tr');
                echo html_writer::start_tag('tr') . html_writer::tag('td', '<br>',array('colspace'=>'4')) . html_writer::end_tag('tr');
                echo html_writer::start_tag('td', array('style'=>'max-width:50px; white-space:nowrap;','colspan'=>'2','align'=>'left'));

                //dummy form - alignment only
                echo html_writer::start_tag('form', array('action'=>'morslefiles.php','method'=>'get'));
                echo html_writer::start_tag('fieldset', array('class'=>'invisiblefieldset'));
                echo html_writer::tag('input', '', array('type'=>'button','value'=>$strselectall,'onclick'=>'checkall();','style'=>'color:green;'));
                echo html_writer::tag('input', '', array('type'=>'button','value'=>$strselectnone,'onclick'=>'checknone();','style'=>'color:red;'));
                echo html_writer::end_tag('fieldset');
                echo html_writer::end_tag('form');
                echo html_writer::end_tag('td');

                echo html_writer::start_tag('td', array('align'=>'center','colspan'=>'2'));

                // makedir form
                        // TODO: program to allow this in user and departmental directory
                if (strpos($wdir,$deptstr) === false && strpos($wdir,$userstr) === false) { // not a user or departmental folder
                    echo html_writer::start_tag('form', array('action'=>'morslefiles.php','method'=>'get'));
                    echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'choose','value'=>$choose));
                    echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'courseid','value'=>$courseid));
                    echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'wdir','value'=>$wdir));
                    echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'action','value'=>'makedir'));
                    echo html_writer::tag('input', '', array('type'=>'submit','value'=>$strmakeafolder));
                    echo html_writer::end_tag('form');
                }
                echo html_writer::end_tag('td');
                echo html_writer::end_tag('tr');
            }
        }
    }
    echo html_writer::start_tag('form', array('action'=>'morslefiles.php','method'=>'post','id'=>'dirform'));
    echo html_writer::start_div();
    echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'choose','value'=>$choose));
    echo html_writer::start_tag('tr');
    echo html_writer::start_tag('th', array('class'=>'header','scope'=>'col','style'=>'max-width:40px;'));
    echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'courseid','value'=>$courseid));
    echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'choose','value'=>$choose));
    echo html_writer::tag('input', '', array('type'=>'hidden','name'=>'wdir','value'=>$wdir));
    echo html_writer::tag('input','', array('type'=>'hidden','name'=>'sesskey','value'=>$USER->sesskey));
//      $options = array ("delete" => "$strdeletefromcollection");
    // only editing teachers can link items to course page
    if (has_capability('moodle/course:update', $coursecontext)) {
        $options['link'] = "$strlinktocourse";
    }
    if (!empty($filelist) || !empty($dirlist)) {

//        echo html_writer::tag('label', "$strwithchosenfiles...", array('for'=>'formactionid'));
//    	  echo html_writer::select($options, "$strwithchosenfiles...", '', array(1 => "$strwithchosenfiles..."));

        echo html_writer::start_div('', array('id'=>'noscriptgo','style'=>'display:inline;'));
        echo html_writer::tag('input','', array('type'=>'submit', 'value'=>get_string('go')));
        echo html_writer::script('document.getElementById("noscriptgo").style.display="none"');
        echo html_writer::end_div();

    }

    echo html_writer::end_tag('th');
    echo html_writer::start_tag('th', array('style'=>'padding-right:120px;','class'=>'header name', 'scope'=>'col')) . html_writer::link(qualified_me(), $strname, array('&sort'=>'{'.$sortvalues[0].'}')) . html_writer::end_tag('th');
    echo html_writer::start_tag('th', array('class'=>'header date','scope'=>'col')) . html_writer::link(qualified_me(), $strmodified, array('&sort'=>'{'.$sortvalues[2].'}')) . html_writer::end_tag('th');;
    echo html_writer::tag('th', $straction, array('class'=>'header commands','scope'=>'col'));
    echo html_writer::end_tag('tr') ."\n";

    // Sort parameter indicates column to sort by, and parity gives the direction
    switch ($sort) {
        case 1:
            $sortcmp = 'return strcasecmp($a[0],$b[0]);';
            break;
        case -1:
            $sortcmp = 'return strcasecmp($b[0],$a[0]);';
            break;
        case 2:
            $sortcmp = 'return ($a[1] - $b[1]);';
            break;
        case -2:
            $sortcmp = 'return ($b[1] - $a[1]);';
            break;
        case 3:
            $sortcmp = 'return ($a[2] - $b[2]);';
            break;
        case -3:
            $sortcmp = 'return ($b[2] - $a[2]);';
            break;
    }

    // Create a 2D array of directories and sort
    $dirdetails = array();
    foreach ($dirlist as $name=>$dir) {
        $dirdetails[$name] = new stdClass();
        $dirdetails[$name]->updated = docdate($dir);
        $dirdetails[$name]->link = $dir->alternateLink;
//        usort($dirdetails, create_function('$a,$b', $sortcmp));
    }

    // TODO: change to handle cross-listed courses
    // TODO: this needs to change if we eliminate morsle table
    if ($wdir === '') {
        $shortname = is_number(substr($course->shortname,0,5)) ? substr($course->shortname, 6) : $course->shortname;
        // SPLIT INTO DEPARTMENTAL CODES
        $dept = explode("-",$shortname);
        $deptpart = defined($dept[0]) ? CONSTANT($dept[0]) : null;
        $deptstr =  $deptpart . $deptstr;
        $deptaccount = strtolower($deptstr);
        // only show the user collection if we're in the base folder
        $dirdetails[$userstr] = new stdClass();
        $dirdetails[$userstr]->updated = date('Y-m-d');
        $dirdetails[$userstr]->link = 'https://drive.google.com';
    
        // always include departmental directory if exists
        // check to see if we even have a departmental account for this department but don't show the departmental collection if we're already in it indicated by $wdir
        if ($is_morsle_dept = $DB->get_record('morsle_active',array('shortname' => $deptaccount))
            && has_capability('moodle/course:update', $coursecontext)) {
            $dirdetails[$deptstr] = new stdClass();
            $dirdetails[$deptstr]->updated = date('Y-m-d');
        }

    }

    // Create a 2D array of files and sort
    $filedetails = array();
    $filetitles = array();
    foreach ($filelist as $name=>$file) {
        $filedetails[$name] = new stdClass();
        $filedetails[$name]->updated = docdate($file);
        $filedetails[$name]->link = $file->alternateLink;
//        $row = array($filename, $filedate);
//		array_push($filedetails, $row);
//		usort($filedetails, create_function('$a,$b', $sortcmp));
    }
    // TODO: fix this hack so we're back to being able to sort
//    ksort($filedetails); // sets the locked in sorting to name
    // need this in order to look up the link for the file based on doc title (key)
/*
    if (sizeof($filelist) > 0) {
            $filevalues = array_values($filelist);
            $filelist = array_combine($filetitles, $filevalues);
    }
*/
//    $count = 0;
//    $countdir = 0;
	$edittext = $padchoose .$padedit . $padunzip . $padlist . $padrestore;

    if ($wdir !== '') {
        $pathparts = explode('/', $wdir);
        array_pop($pathparts);
        $wdir = implode('/', $pathparts);
        echo "<tr class=\"folder\">";
        print_cell();
        print_cell('left', '<a href="morslefiles.php?courseid=' . $courseid . '&amp;wdir=' . $wdir . '&amp;choose=' . $choose . '&amp;name=' . $name . '"><img src="'.$OUTPUT->pix_url('f/folder').'" class="icon" alt="" />&nbsp;'. get_string('parentfolder') .'</a>', 'name');
//        print_cell('left', '<a  style="padding-left:0px" href="morslefiles.php?courseid='.$courseid.'&amp;wdir='.$wdir.'/&amp;choose='.$choose.'">&nbsp;'.get_string('parentfolder').'</a>', 'parent');
        echo "</tr>";
    }
    if (!empty($dirdetails)) {
        foreach ($dirdetails as $name => $dir) {
            echo html_writer::start_tag('tr', array('class'=>'folder'));
            $filedate = $dir->updated;
            $filesafe = rawurlencode($name);
            $filename = $name;
            $fileurl = $dir->link;

//           	$countdir++;
            // TODO: fix the parent directory
            if ($name == '..') {
//                $fileurl = rawurlencode(dirname($wdir));
                print_cell();
                // alt attribute intentionally empty to prevent repetition in screen reader
				//CLAMP #289 change padding-left from 10 to 0px
				//Kevin Wiliarty 2011-03-08
                print_cell('left', '<a  style="padding-left:0px" href="morslefiles.php?courseid='.$courseid.'&amp;wdir='.$wdir.'/'.$fileurl.'&amp;choose='.$choose.'"><img src="'.$OUTPUT->pix_url('f/parent.gif').'" class="icon" alt="" />&nbsp;'.get_string('parentfolder').'</a>', 'name');
                print_cell();
                print_cell();
                print_cell();
/*
            } else if ($name === $userstr) { // if departmental account or user collection
            	// TODO: need to determine what $wdir is if we're coming in from one of the course subcollections
                // don't know where this fits in
		$branchdir = strpos($wdir,'read') !== false || strpos($wdir,'write') !== false  || $wdir === '' ? $filesafe : "$wdir/$filesafe";
                 print_cell();
                // alt attribute intentionally empty to prevent repetition in screen reader
				//CLAMP #289 change padding-left from 10 to 0px
				//Kevin Wiliarty 2011-03-08
                print_cell('left', '<a  style="padding-left:0px" href="morslefiles.php?courseid=' . $courseid . '&amp;wdir=' . $wdir . '&amp;choose=' . $choose .'&amp;name=' . $name . '"><img src="'.$OUTPUT->pix_url('f/folder').'" class="icon" alt="" />&nbsp;'. $name .'</a>', 'name');
//                print_cell('left', '<a  style="padding-left:0px" href="' . $fileurl . '" target="_blank"><img src="'. $OUTPUT->pix_url("f/folder") .'" class="icon" alt="" />&nbsp;'. $filename .'</a>');
                print_cell("right", $filedate, 'date');
//                print_cell();
                print_cell();
//              print_cell();
            } else if ($name === $deptstr){
            	// TODO: need to determine what $wdir is if we're coming in from one of the course subcollections
		$branchdir = strpos($wdir,'read') !== false || strpos($wdir,'write') !== false  || $wdir === '' ? $filesafe : "$wdir/$filesafe";
            	print_cell("center", "<input type=\"checkbox\" name=\"dir$countdir\" value=\"$filename\" />", 'checkbox');
                // alt attribute intentionally empty to prevent repetition in screen reader
				//CLAMP #289 change padding-left from 10 to 0px
				//Kevin Wiliarty 2011-03-08
                print_cell('left', '<a  style="padding-left:0px" href="morslefiles.php?courseid=' . $courseid . '&amp;wdir=' . $branchdir . '&amp;choose=' . $choose . '&amp;name=' . $name . '"><img src="'.$OUTPUT->pix_url('f/folder').'" class="icon" alt="" />&nbsp;'. $name .'</a>', 'name');
                print_cell("right", $filedate, 'date');
//                print_cell();
				if (has_capability('moodle/course:update', $coursecontext)) {
	                print_cell("left", "$edittext<a href=\"morslefiles.php?courseid=$courseid&amp;wdir=$branchdir&amp;file=$filename&amp;action=link&amp;type=dir&amp;choose=$choose\">$strlinktocourse</a>", 'commands');
				}
//              print_cell();
*/
            } else { // not a user or departmental folder
                print_cell();
//               	print_cell("center", "<input type=\"checkbox\" name=\"$name\" value=\"$filename\" />", 'checkbox');
//                print_cell("left", "<a href=\"morslefiles.php?courseid=$courseid&amp;wdir=$wdir/$filesafe&amp;choose=$choose\"><img src=\"$OUTPUT->pix_url('f/folder')\" class=\"icon\" alt=\"$strfolder\" />&nbsp;".$filename."</a>", 'name');
                $branchdir = "$wdir/$filesafe";
//                $branchdir = strpos($wdir,'read') !== false || strpos($wdir,'write') !== false  || $wdir === '' ? $filesafe : "$wdir/$filesafe";
                print_cell('left', '<a href="morslefiles.php?courseid=' . $courseid . '&amp;wdir=' . $branchdir . '&amp;choose=' . $choose . '&amp;name=' . $name . '"><img src="'.$OUTPUT->pix_url('f/folder').'" class="icon" alt="" />&nbsp;'. $filename .'</a>', 'name');
                print_cell("right", $filedate, 'date');
//                print_cell();
                if (has_capability('moodle/course:update', $coursecontext)) {
                    print_cell("left", "$edittext<a href=\"morslefiles.php?courseid=$courseid&amp;wdir=$branchdir&amp;file=$filename&amp;action=link&amp;type=dir&amp;choose=$choose\">$strlinktocourse</a>", 'commands');
//                    print_cell("left", "$edittext<a href=\"morslefiles.php?courseid=$courseid&amp;wdir=$wdir&amp;file=$filename&amp;action=link&amp;type=dir&amp;choose=$choose\">$strlinktocourse</a>", 'commands');
                }
            }

            echo html_writer::end_tag('tr');
        }
    }

    $iconchoices = array('excel'=>'download/spreadsheets','powerpoint'=>'download/presentations','word'=>'download/documents',
    		'pdf'=>'application/pdf');
    if (!empty($filedetails)) {
        foreach ($filedetails as $name => $file) {

            if (isset($filelist[$name]->exportLinks)) {
                $links = array();
                $links = array_values($filelist[$name]->exportLinks);
                $exportlink = $links[0];
            } else {
                $exportlink = $filelist[$name]->alternateLink;
            }
            // positively identify the correct icon regardless of filename extension
            $icon = $filelist[$name]->iconLink;
            $filename = $name;
            $fileurl = $file->link;
            $embedlink = $filelist[$name]->embedLink;
            $embedsafe = rawurlencode($embedlink);
            $fileurlsafe = rawurlencode($fileurl);
            $filedate    = $file->updated;
            $fileid = $filelist[$name]->id;
            $selectfile = trim($fileurl, "/");

//            echo html_writer::start_tag('td', array('class'=>'file'));
//            echo html_writer::end_tag('td');

            print_cell("center", "<input type=\"checkbox\" name=\"file\" value=\"$filename\" />", 'checkbox');
	    //CLAMP #289 change padding-left from 10 to 0px
	    //Kevin Wiliarty 2011-03-08
//            echo html_writer::start_tag('td', array('align'=>'left','style'=>'white-space:nowrap; padding-left:0px;','class'=>'name'));
            
            print_cell('left', '<a href="' . $fileurl . '" class="morslefile" target="_blank">
            		<img src="' . $icon . '" class="icon" alt="' . $strfile . '" /> ' . $filename . '</a>', 'name');
//            $echovar = '<a href="' . $fileurl . '" target="_blank">
//            		<img src="' . $OUTPUT->pix_url("f/$icon") . '" class="icon" alt="' . $strfile . '" />&nbsp;' . htmlspecialchars($filename) . '</a>';
//            echo $echovar;
//html_writer::link(qualified_me(), $strname, array('&sort'=>'{'.$sortvalues[0].'}'))
            //$echovar = html_writer::tag('a', $fileurl, array('target'=>'_blank')) . html_writer::img($OUTPUT->pix_url("f/$icon"), $strfile, array('class'=>'icon')) . '&nbsp;'.htmlspecialchars($filename) . html_writer::end_tag('a');
            //echo $echovar;
//            echo html_writer::end_tag('td');

            print_cell("right", $filedate, 'date');
            if (has_capability('moodle/course:update', $coursecontext)) {
                if (strpos($wdir, $gdocsstr) === 1) {
                    print_cell("left", "$edittext <a href=\"morslefiles.php?courseid=$courseid&amp;wdir=$wdir/$fileid&amp;file=$filename&amp;name=$filename&amp;filelink=$fileurl&amp;action=link&amp;type=file&amp;choose=$choose\">$strlinktocourse</a>", 'commands');
                } else {
                    print_cell("left", "$edittext <a href=\"morslefiles.php?courseid=$courseid&amp;wdir=$wdir&amp;file=$filename&amp;action=link&amp;type=file&amp;choose=$choose\">$strlinktocourse</a>", 'commands');
                 }
//                print_cell('left', '&nbsp&nbsp<a title="' . $name . '" href="embeddoc.php?courseid=$courseid&amp;embedlink=' . $embedsafe . '&amp;name=' . $filename . '"> Embed </a>','embed');
            }
//            print_cell('left', '&nbsp&nbsp<a title="' . strip_tags($strdownload) . ': ' . $name . '" href="' .$CFG->wwwroot
//                    . '/blocks/morsle/docs_export.php?exportlink=' . s($exportlink) . '&shortname=' . $course->shortname . '&title=' . $filename . '" target="_blank"> Download </a>','commands');
            print_cell();
            print_cell('left', '&nbsp&nbsp<a title="' . $name . '" href="' . s($exportlink) . '" target="_blank"> Download </a>','commands');
            //print_cell('left', '&nbsp&nbsp<a title="' . $name . '" href="embeddoc.php?"' . s($embedlink) . '" target="_blank"> Embed in a Page resource </a>','commands');

           echo html_writer::end_tag('tr');
        }
    }
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('table');
}

function clearfilelist() {
    global $USER;

    $USER->filelist = array ();
    $USER->fileop = "";
}


function docdate($file) {
	// TODO: fix this
//	return '';
    return substr($file->modifiedDate,5,2) . '/' . substr($file->modifiedDate,8,2) . '/' . substr($file->modifiedDate,0,4) . ' - ' . substr($file->modifiedDate,11,8);
}

function is_folder($file) {
    return strpos($file->mimeType,'folder');
}

function printfilelist($filelist) {
    global $CFG, $basedir;

    $strfolder = get_string("folder");
    $strfile   = get_string("file");

    foreach ($filelist as $file) {
        if (is_dir($basedir.'/'.$file)) {
            echo '<img src="'. $OUTPUT->pix_url('f/folder') . '" class="icon" alt="'. $strfolder .'" /> '. htmlspecialchars($file) .'<br />';
            $subfilelist = array();
            $currdir = opendir($basedir.'/'.$file);
            while (false !== ($subfile = readdir($currdir))) {
                if ($subfile <> ".." && $subfile <> ".") {
                    $subfilelist[] = $file."/".$subfile;
                }
            }
            printfilelist($subfilelist);

        } else {
            $icon = mimeinfo("icon", $file);
            echo '<img src="'. $OUTPUT->pix_url("f/$icon") .'" class="icon" alt="'. $strfile .'" /> '. htmlspecialchars($file) .'<br />';
        }
    }
}


function print_cell($alignment='center', $text='&nbsp;', $class='') {
    if ($class) {
        $class = ' class="'.$class.'"';
    }
    echo '<td align="'.$alignment.'" style="white-space:nowrap "'.$class.'>'.$text.'</td>';
}

function html_footer() {
	global $COURSE, $OUTPUT;

	echo '</td></tr></table>';

	echo $OUTPUT->footer();
}

function html_header($course, $wdir, $pagetitle="", $formfield=""){
	global $CFG, $ME, $choose, $COURSE, $OUTPUT, $PAGE;
        $coursecontext = context_course::instance($COURSE->id);
	$navlinks = array();
	// $navlinks[] = array('name' => $course->shortname, 'link' => "../course/view.php?id=$course->id", 'type' => 'misc');

	$strfiles = get_string("morslefiles", 'block_morsle');

	$dirs = explode("/", $wdir);
	$numdirs = count($dirs);
	$link = "";
	if (has_capability('moodle/course:update', $coursecontext)) {
		$navlinks[] = array('name' => $strfiles,
							'link' => $ME."?id=$course->id&amp;wdir=/&amp;choose=$choose",
							'type' => 'misc');
	}

	for ($i=1; $i<$numdirs-1; $i++) {
		$link .= "/".urlencode($dirs[$i]);
		$navlinks[] = array('name' => $dirs[$i],
							'link' => $ME."?id=$course->id&amp;wdir=$link&amp;choose=$choose",
							'type' => 'misc');
	}
	$navlinks[] = array('name' => $dirs[$numdirs-1], 'link' => null, 'type' => 'misc');
	$navigation = build_navigation($navlinks);

	if ($choose) {
		print_header();

		$chooseparts = explode('.', $choose);
		if (count($chooseparts)==2){
		?>
		<script type="text/javascript">
		//<![CDATA[
		function set_value(txt) {
			opener.document.forms['<?php echo $chooseparts[0]."'].".$chooseparts[1] ?>.value = txt;
			window.close();
		}
		//]]>
		</script>

		<?php
		} elseif (count($chooseparts)==1){
		?>
		<script type="text/javascript">
		//<![CDATA[
		function set_value(txt) {
			opener.document.getElementById('<?php echo $chooseparts[0] ?>').value = txt;
			window.close();
		}
		//]]>
		</script>

		<?php

		}
		$fullnav = '';
		$i = 0;
		foreach ($navlinks as $navlink) {
			// If this is the last link do not link
			if ($i == count($navlinks) - 1) {
				$fullnav .= $navlink['name'];
			} else {
				$fullnav .= '<a href="'.$navlink['link'].'">'.$navlink['name'].'</a>';
			}
			$fullnav .= ' -> ';
			$i++;
		}
		$fullnav = substr($fullnav, 0, -4);
		$fullnav = str_replace('->', '&raquo;', format_string($course->shortname) . " -> " . $fullnav);
		echo '<div id="nav-bar">'.$fullnav.'</div>';

		if ($course->id == SITEID and $wdir != "/backupdata") {
			print_heading(get_string("publicsitefileswarning3"), "center", 2);
		}

	} else {

		if ($course->id == SITEID) {

			if ($wdir == "/backupdata") {
				admin_externalpage_setup('frontpagerestore');
				admin_externalpage_print_header();
			} else {
				admin_externalpage_setup('sitefiles');
				admin_externalpage_print_header();

				print_heading(get_string("publicsitefileswarning3"), "center", 2);

			}

		} else {
			echo $OUTPUT->header();
//			print_header($pagetitle, $course->fullname, $navigation,  $formfield);
		}
	}


	echo "<table border=\"0\" style=\"margin-left:auto;margin-right:auto;min-width:100%\" cellspacing=\"3\" cellpadding=\"3\" >";
	echo "<tr>";
	echo "<td colspan=\"2\">";
        die;

}

/*
 * @param $var - role to check
* check if role passed is an owner role
*/
function is_owner($var) {
	return in_array($var->role, array('editingteacher','teacher'));
}

/*
 * @param $var - role to check
* check if role passed is an owner role
* TODO: not sure why we need this, what's with sending $var and not the role?
*/
function full_is_owner($var) {
	return in_array($var->role, array('editingteacher','teacher'));
}

?>